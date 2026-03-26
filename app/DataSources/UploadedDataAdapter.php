<?php

namespace App\DataSources;

use App\Contracts\DataSourceAdapterInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class UploadedDataAdapter implements DataSourceAdapterInterface
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? storage_path('app/data_sources/uploads');
    }

    public function name(): string
    {
        return 'Uploaded files';
    }

    public function fetchRecords(string $dataType, ?string $modifiedSince = null): array
    {
        $meta = $this->readMetadata($dataType);
        if ($meta === null) {
            Log::channel('integration')->debug('UploadedDataAdapter: no metadata found', ['data_type' => $dataType]);
            return [];
        }

        $path = $this->basePath . '/' . $dataType . '.' . ($meta['extension'] ?? 'csv');
        if (! is_readable($path)) {
            Log::channel('integration')->debug('UploadedDataAdapter: file not readable', ['path' => $path]);
            return [];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            return $this->readJson($path);
        }

        return $this->readCsv($path);
    }

    /**
     * Stream records in chunks to avoid loading entire file into memory.
     *
     * @return \Generator<int, array<int, array>>
     */
    public function fetchRecordsInChunks(string $dataType, int $chunkSize = 200): \Generator
    {
        $meta = $this->readMetadata($dataType);
        if ($meta === null) {
            Log::channel('integration')->debug('UploadedDataAdapter: no metadata found', ['data_type' => $dataType]);
            return;
        }

        $path = $this->basePath . '/' . $dataType . '.' . ($meta['extension'] ?? 'csv');
        if (! is_readable($path)) {
            Log::channel('integration')->debug('UploadedDataAdapter: file not readable', ['path' => $path]);
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            yield from $this->readJsonInChunks($path, $chunkSize);
            return;
        }

        yield from $this->readCsvInChunks($path, $chunkSize);
    }

    public function supports(string $dataType): bool
    {
        return $this->readMetadata($dataType) !== null;
    }

    protected function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_pad($row, count($header), null));
        }
        fclose($handle);

        return $rows;
    }

    protected function readJson(string $path): array
    {
        $raw = File::get($path);
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return [];
        }
        if (array_is_list($json)) {
            return array_filter($json, fn ($row) => is_array($row));
        }
        if (isset($json['records']) && is_array($json['records'])) {
            return array_filter($json['records'], fn ($row) => is_array($row));
        }
        if (isset($json['data']) && is_array($json['data'])) {
            return array_filter($json['data'], fn ($row) => is_array($row));
        }
        return [];
    }

    /**
     * @return \Generator<int, array<int, array>>
     */
    protected function readCsvInChunks(string $path, int $chunkSize): \Generator
    {
        $chunkSize = max(1, $chunkSize);
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return;
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_pad($row, count($header), null));
            if (count($rows) >= $chunkSize) {
                yield $rows;
                $rows = [];
            }
        }
        if ($rows !== []) {
            yield $rows;
        }
        fclose($handle);
    }

    /**
     * @return \Generator<int, array<int, array>>
     */
    protected function readJsonInChunks(string $path, int $chunkSize): \Generator
    {
        $chunkSize = max(1, $chunkSize);
        $raw = File::get($path);
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return;
        }
        if (array_is_list($json)) {
            $records = array_values(array_filter($json, fn ($row) => is_array($row)));
        } elseif (isset($json['records']) && is_array($json['records'])) {
            $records = array_values(array_filter($json['records'], fn ($row) => is_array($row)));
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $records = array_values(array_filter($json['data'], fn ($row) => is_array($row)));
        } else {
            return;
        }

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            yield $chunk;
        }
    }

    protected function readMetadata(string $dataType): ?array
    {
        $metaPath = $this->basePath . '/' . $dataType . '.meta.json';
        if (! File::exists($metaPath)) {
            return null;
        }
        $raw = File::get($metaPath);
        $json = json_decode($raw, true);
        return is_array($json) ? $json : null;
    }
}
