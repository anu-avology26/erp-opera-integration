<?php

namespace App\DataSources;

use App\Contracts\DataSourceAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseAdapter implements DataSourceAdapterInterface
{
    public function __construct(
        protected ?string $connection = null,
        protected array $tableMap = []
    ) {
        $this->connection = $connection ?? config('database.default');
        $this->tableMap = $tableMap ?: config('integration.db_table_map', [
            'ar_accounts' => 'erp_customers',
            'reservations' => 'reservations',
            'guest_profiles' => 'guest_profiles',
            'rates' => 'rates',
            'inventory' => 'inventory',
        ]);
    }

    public function name(): string
    {
        return 'Database (MySQL/PostgreSQL)';
    }

    public function fetchRecords(string $dataType, ?string $modifiedSince = null): array
    {
        if (! $this->supports($dataType)) {
            return [];
        }

        $table = $this->tableMap[$dataType] ?? null;
        if (! $table) {
            return [];
        }

        try {
            $query = DB::connection($this->connection)->table($table);
            if ($modifiedSince !== null && $modifiedSince !== '') {
                $query->where('updated_at', '>=', $modifiedSince);
            }

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            Log::channel('integration')->warning('DatabaseAdapter: query failed', [
                'data_type' => $dataType,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function supports(string $dataType): bool
    {
        return isset($this->tableMap[$dataType]);
    }
}
