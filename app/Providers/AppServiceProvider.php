<?php

namespace App\Providers;

use App\Contracts\DataSourceAdapterInterface;
use App\DataSources\CsvDataAdapter;
use App\DataSources\DatabaseAdapter;
use App\DataSources\ErpRestApiAdapter;
use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Mapping\ErpCustomerMapper;
use App\Services\Opera\OperaOhipClient;
use App\Services\PayloadAuditService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BusinessCentralApiClient::class, fn () => BusinessCentralApiClient::fromConfig());
        $this->app->singleton(OperaOhipClient::class, fn () => OperaOhipClient::fromConfig());
        $this->app->singleton(ErpCustomerMapper::class, fn () => new ErpCustomerMapper);
        $this->app->singleton(PayloadAuditService::class, fn () => PayloadAuditService::fromConfig());

        $this->app->bind(DataSourceAdapterInterface::class, function ($app) {
            $adapter = config('integration.data_source_adapter', 'erp_rest_api');
            return match ($adapter) {
                'csv' => new CsvDataAdapter,
                'database' => new DatabaseAdapter,
                default => $app->make(ErpRestApiAdapter::class),
            };
        });
        $this->app->singleton(ErpRestApiAdapter::class, fn ($app) => new ErpRestApiAdapter(
            $app->make(BusinessCentralApiClient::class),
            $app->make(ErpCustomerMapper::class)
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
