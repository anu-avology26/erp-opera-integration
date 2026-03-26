<?php

namespace App\Contracts;

interface DataSourceAdapterInterface
{
    /**
     * Human-readable name of the data source.
     */
    public function name(): string;

    /**
     * Fetch records for the given data type.
     *
     * @param  string  $dataType  e.g. ar_accounts, reservations, guest_profiles, rates, inventory
     * @param  string|null  $modifiedSince  ISO 8601 timestamp for incremental sync
     * @return array<int, array>
     */
    public function fetchRecords(string $dataType, ?string $modifiedSince = null): array;

    /**
     * Whether this adapter supports the given data type.
     */
    public function supports(string $dataType): bool;
}
