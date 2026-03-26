<?php

namespace App\DataSources;

use App\Contracts\DataSourceAdapterInterface;
use Illuminate\Support\Facades\Log;

class CsvDataAdapter implements DataSourceAdapterInterface
{
    public function __construct(
        protected ?string $basePath = null
    ) {
        $this->basePath = $basePath ?? storage_path('app/data_sources/csv');
    }

    public function name(): string
    {
        return 'CSV files';
    }

    public function fetchRecords(string $dataType, ?string $modifiedSince = null): array
    {
        if (! $this->supports($dataType)) {
            return [];
        }

        $path = $this->basePath . '/' . $dataType . '.csv';
        if (! is_readable($path)) {
            Log::channel('integration')->debug('CsvDataAdapter: file not found or not readable', ['path' => $path]);

            return [];
        }

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

    public function supports(string $dataType): bool
    {
        $supported = config('integration.csv_data_types', ['reservations', 'guest_profiles', 'rates', 'inventory']);

        return in_array($dataType, $supported, true);
    }
}
