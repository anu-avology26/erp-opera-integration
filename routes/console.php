<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Models\ErpCustomer;
use App\Models\IntegrationSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('postman:export-erp {--limit=10 : Number of latest ERP customers to export}', function () {
    $limit = (int) $this->option('limit');
    $limit = $limit > 0 ? $limit : 10;

    $propertyIds = (array) config('opera.property_ids', []);
    $defaultHotelId = $propertyIds[0] ?? '';

    $records = ErpCustomer::query()
        ->orderByDesc('last_modified_at')
        ->orderByDesc('id')
        ->limit($limit)
        ->get();

    $rows = [];
    foreach ($records as $row) {
        $hotelId = $row->property ?: $defaultHotelId;
        $accountNo = $row->ar_number ?: $row->erp_number;
        $rows[] = [
            'erp_number' => (string) $row->erp_number,
            'company_name' => (string) $row->name,
            'address_1' => (string) ($row->address_1 ?? ''),
            'address_2' => (string) ($row->address_2 ?? ''),
            'city' => (string) ($row->payload['city'] ?? $row->payload['cityName'] ?? ''),
            'state' => (string) ($row->payload['state'] ?? $row->payload['stateName'] ?? ''),
            'post_code' => (string) ($row->post_code ?? ''),
            'country' => (string) ($row->country ?? ''),
            'email' => (string) ($row->email ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'vat' => (string) ($row->vat_registration_no ?? ''),
            'payment_terms' => (string) ($row->payment_terms_code ?? ''),
            'credit_limit' => (string) ($row->credit_limit ?? 0),
            'restricted' => 'false',
            'account_no' => (string) $accountNo,
            'opera_hotel_id' => (string) $hotelId,
        ];
    }

    $outDir = base_path('docs/postman');
    if (! File::exists($outDir)) {
        File::makeDirectory($outDir, 0755, true);
    }

    $jsonPath = $outDir . '/erp-latest-' . $limit . '.json';
    File::put($jsonPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $csvPath = $outDir . '/erp-latest-' . $limit . '.csv';
    $fh = fopen($csvPath, 'w');
    if ($fh !== false) {
        if (! empty($rows)) {
            fputcsv($fh, array_keys($rows[0]));
            foreach ($rows as $r) {
                fputcsv($fh, $r);
            }
        }
        fclose($fh);
    }

    $this->info('Exported ' . count($rows) . ' records:');
    $this->info($jsonPath);
    $this->info($csvPath);
})->purpose('Export latest ERP customers for Postman Runner data');

$schedules = config('integration.schedules', []);
if (empty($schedules)) {
    $schedules = [
        ['data_type' => 'ar_accounts', 'property_id' => null, 'time' => '02:00'],
    ];
}
$overrideTime = null;
$overrideFrequency = null;
$overrideCron = null;
try {
    if (Schema::hasTable('integration_settings')) {
        $overrideTime = IntegrationSetting::getValue('sync_time');
        $overrideFrequency = IntegrationSetting::getValue('sync_frequency');
        $overrideCron = IntegrationSetting::getValue('sync_cron');
    }
} catch (\Throwable) {
    $overrideTime = null;
    $overrideFrequency = null;
    $overrideCron = null;
}
foreach ($schedules as $schedule) {
    $dataType = $schedule['data_type'] ?? null;
    $command = $dataType ? config("integration.data_types.{$dataType}.command") : null;
    $time = $schedule['time'] ?? '02:00';
    if ($dataType === 'ar_accounts' && is_string($overrideTime) && $overrideTime !== '') {
        $time = $overrideTime;
    }
    if ($command) {
        $event = Schedule::command($command)
            ->withoutOverlapping(30)
            ->runInBackground();

        if ($dataType === 'ar_accounts' && is_string($overrideFrequency) && $overrideFrequency !== '') {
            switch ($overrideFrequency) {
                case 'every_5_minutes':
                    $event->everyFiveMinutes();
                    break;
                case 'every_10_minutes':
                    $event->everyTenMinutes();
                    break;
                case 'every_15_minutes':
                    $event->everyFifteenMinutes();
                    break;
                case 'every_30_minutes':
                    $event->everyThirtyMinutes();
                    break;
                case 'hourly':
                    $event->hourly();
                    break;
                case 'every_2_hours':
                    $event->everyTwoHours();
                    break;
                case 'every_4_hours':
                    $event->everyFourHours();
                    break;
                case 'every_6_hours':
                    $event->everySixHours();
                    break;
                case 'every_12_hours':
                    $event->cron('0 */12 * * *');
                    break;
                case 'weekly':
                    $event->weeklyOn(1, $time);
                    break;
                case 'monthly':
                    $event->monthlyOn(1, $time);
                    break;
                case 'custom_cron':
                    if (is_string($overrideCron) && $overrideCron !== '') {
                        $event->cron($overrideCron);
                        break;
                    }
                    $event->dailyAt($time);
                    break;
                case 'daily_at':
                default:
                    $event->dailyAt($time);
                    break;
            }
        } else {
            $event->dailyAt($time);
        }
    }
}
// Testing only (run every 2 minutes):
// Schedule::command('sync:erp-opera-accounts')->everyTwoMinutes()->withoutOverlapping(30)->runInBackground();

Schedule::call(function () {
    app(\App\Services\PayloadAuditService::class)->cleanupExpired();
})->daily()->at('03:00');
