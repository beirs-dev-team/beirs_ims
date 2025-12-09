<?php

namespace App\Providers;

use App\Interfaces\BlotterReportRepositoryInterface;
use App\Interfaces\CertificateRepositoryInterface;
use App\Interfaces\UsersRepositoryInterface;
use App\Repositories\BlotterReportRepositoryImpl;
use App\Repositories\CertificateRepositoryImpl;
use App\Repositories\UsersRepositoryImpl;
use App\Services\TwilioClient;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(UsersRepositoryInterface::class, UsersRepositoryImpl::class);
        $this->app->bind(CertificateRepositoryInterface::class, CertificateRepositoryImpl::class);
        $this->app->bind(BlotterReportRepositoryInterface::class, BlotterReportRepositoryImpl::class);
        $this->app->singleton(TwilioClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(UrlGenerator $url)
    {
        if (env('APP_ENV') === 'production') {
            $url->forceScheme('https');
        }
    }
}
