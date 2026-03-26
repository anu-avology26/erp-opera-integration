<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncUploadedArAccountsJob;
use App\Services\Mapping\ErpMappingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UploadController extends Controller
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = storage_path('app/data_sources/uploads');
    }

    public function index(Request $request)
    {
        $meta = $this->readMetadata('ar_accounts');
        $previewRows = [];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;
        $totalPages = 1;
        $baseIndex = 0;

        if ($meta) {
            $total = (int) ($meta['row_count'] ?? 0);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $ext = $meta['extension'] ?? null;
            if ($ext) {
                $path = $this->basePath . '/ar_accounts.' . $ext;
                if (File::exists($path)) {
                    $previewRows = $this->readPage($path, $ext, $page, $perPage);
                    $baseIndex = ($page - 1) * $perPage;
                }
            }
        }

        return view('admin.uploads', [
            'meta' => $meta,
            'previewRows' => $previewRows,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'baseIndex' => $baseIndex,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'data_file' => 'required|file|max:51200',
        ]);

        $file = $request->file('data_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'json'], true)) {
            return back()->with('message', 'Only CSV or JSON files are supported.');
        }

        if (! File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }

        $dataType = 'ar_accounts';
        $targetPath = $this->basePath . '/' . $dataType . '.' . $ext;
        $file->move($this->basePath, $dataType . '.' . $ext);

        $parsed = $this->parseFile($targetPath, $ext);
        $columns = $parsed['columns'];
        $preview = $parsed['preview'];
        $rowCount = $parsed['row_count'];
        $errors = $parsed['errors'];

        $mapping = app(ErpMappingConfig::class)->customerFieldKeys();
        $missing = $this->missingRequiredColumns($columns, $mapping);
        if ($missing !== []) {
            $errors[] = 'Missing required columns for mapping: ' . implode(', ', $missing);
        }

        $meta = [
            'data_type' => $dataType,
            'extension' => $ext,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toDateTimeString(),
            'row_count' => $rowCount,
            'columns' => $columns,
            'preview' => $preview,
            'errors' => $errors,
        ];

        $this->writeMetadata($dataType, $meta);

        $message = $errors === [] ? 'Upload complete. Preview generated.' : 'Upload complete with warnings.';
        return back()->with('message', $message);
    }

    public function show(int $index)
    {
        $meta = $this->readMetadata('ar_accounts');
        if (! $meta) {
            return redirect()->route('admin.uploads.index')
                ->with('message', 'No uploaded file found. Please upload a CSV or JSON file first.');
        }

        $ext = $meta['extension'] ?? null;
        if (! $ext) {
            return redirect()->route('admin.uploads.index')
                ->with('message', 'Upload metadata is missing file extension.');
        }

        $path = $this->basePath . '/ar_accounts.' . $ext;
        if (! File::exists($path)) {
            return redirect()->route('admin.uploads.index')
                ->with('message', 'Uploaded file not found on disk.');
        }

        $record = $this->readRecord($path, $ext, $index);
        if ($record === null) {
            return redirect()->route('admin.uploads.index')
                ->with('message', 'Requested record not found in uploaded file.');
        }

        return view('admin.upload-record', [
            'meta' => $meta,
            'recordIndex' => $index,
            'record' => $record,
        ]);
    }

    public function run(Request $request)
    {
        $meta = $this->readMetadata('ar_accounts');
        if (! $meta) {
            return back()->with('message', 'No uploaded file found. Please upload a CSV or JSON file first.');
        }
        SyncUploadedArAccountsJob::dispatch();
        return back()->with('message', 'Upload sync job dispatched to integration queue.');
    }

    protected function parseFile(string $path, string $ext): array
    {
        if ($ext === 'json') {
            return $this->parseJson($path);
        }
        return $this->parseCsv($path);
    }

    protected function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['columns' => [], 'preview' => [], 'row_count' => 0, 'errors' => ['Unable to read CSV file.']];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['columns' => [], 'preview' => [], 'row_count' => 0, 'errors' => ['CSV file is empty.']];
        }
        $header = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $header);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_pad($row, count($header), null));
            $count++;
            if ($count >= 5) {
                break;
            }
        }
        fclose($handle);

        $rowCount = $this->countCsvRows($path);

        return [
            'columns' => $header,
            'preview' => $rows,
            'row_count' => $rowCount,
            'errors' => [],
        ];
    }

    protected function parseJson(string $path): array
    {
        $raw = File::get($path);
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return ['columns' => [], 'preview' => [], 'row_count' => 0, 'errors' => ['Invalid JSON file.']];
        }
        $records = [];
        if (array_is_list($json)) {
            $records = array_values(array_filter($json, fn ($row) => is_array($row)));
        } elseif (isset($json['records']) && is_array($json['records'])) {
            $records = array_values(array_filter($json['records'], fn ($row) => is_array($row)));
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $records = array_values(array_filter($json['data'], fn ($row) => is_array($row)));
        }

        if ($records === []) {
            return ['columns' => [], 'preview' => [], 'row_count' => 0, 'errors' => ['JSON file contains no records array.']];
        }

        $columns = array_keys($records[0]);
        $preview = array_slice($records, 0, 5);

        return [
            'columns' => $columns,
            'preview' => $preview,
            'row_count' => count($records),
            'errors' => [],
        ];
    }

    protected function countCsvRows(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }
        $count = 0;
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return 0;
        }
        while (($row = fgetcsv($handle)) !== false) {
            $count++;
        }
        fclose($handle);
        return $count;
    }

    protected function missingRequiredColumns(array $columns, array $mapping): array
    {
        $requiredGroups = [
            'opera_profile_id' => ['account_id', 'accountid', 'account id', 'profileid', 'companyid'],
            'ar_number' => ['ar_number', 'ar number', 'arnumber', 'accountno', 'account_no'],
        ];
        $missing = [];
        $columnsLower = array_map(fn ($v) => strtolower(trim((string) $v)), $columns);

        foreach ($requiredGroups as $group => $defaults) {
            $keys = $mapping[$group] ?? [];
            if (! is_array($keys)) {
                $keys = [];
            }
            $keys = array_values(array_filter(array_map(
                static fn ($value) => is_string($value) ? strtolower(trim($value)) : '',
                array_merge($keys, $defaults)
            )));

            $matched = false;
            foreach (array_unique($keys) as $key) {
                if (in_array($key, $columnsLower, true)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $missing[] = $group === 'opera_profile_id' ? 'Account ID' : 'AR Number';
            }
        }

        return $missing;
    }

    protected function readPage(string $path, string $ext, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        if ($ext === 'json') {
            $raw = File::get($path);
            $json = json_decode($raw, true);
            if (! is_array($json)) {
                return [];
            }
            $records = [];
            if (array_is_list($json)) {
                $records = array_values(array_filter($json, fn ($row) => is_array($row)));
            } elseif (isset($json['records']) && is_array($json['records'])) {
                $records = array_values(array_filter($json['records'], fn ($row) => is_array($row)));
            } elseif (isset($json['data']) && is_array($json['data'])) {
                $records = array_values(array_filter($json['data'], fn ($row) => is_array($row)));
            }
            return array_slice($records, $offset, $perPage);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $header = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $header);

        $rows = [];
        $current = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($current >= $offset && count($rows) < $perPage) {
                $rows[] = array_combine($header, array_pad($row, count($header), null));
            }
            $current++;
            if (count($rows) >= $perPage) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    protected function readRecord(string $path, string $ext, int $index): ?array
    {
        if ($index < 0) {
            return null;
        }

        if ($ext === 'json') {
            $raw = File::get($path);
            $json = json_decode($raw, true);
            if (! is_array($json)) {
                return null;
            }
            $records = [];
            if (array_is_list($json)) {
                $records = array_values(array_filter($json, fn ($row) => is_array($row)));
            } elseif (isset($json['records']) && is_array($json['records'])) {
                $records = array_values(array_filter($json['records'], fn ($row) => is_array($row)));
            } elseif (isset($json['data']) && is_array($json['data'])) {
                $records = array_values(array_filter($json['data'], fn ($row) => is_array($row)));
            }
            return $records[$index] ?? null;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return null;
        }
        $header = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $header);
        $current = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($current === $index) {
                fclose($handle);
                return array_combine($header, array_pad($row, count($header), null));
            }
            $current++;
        }
        fclose($handle);
        return null;
    }

    protected function readMetadata(string $dataType): ?array
    {
        $path = $this->basePath . '/' . $dataType . '.meta.json';
        if (! File::exists($path)) {
            return null;
        }
        $raw = File::get($path);
        $json = json_decode($raw, true);
        return is_array($json) ? $json : null;
    }

    protected function writeMetadata(string $dataType, array $meta): void
    {
        $path = $this->basePath . '/' . $dataType . '.meta.json';
        File::put($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
