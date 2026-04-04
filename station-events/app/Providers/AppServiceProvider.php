<?php

namespace App\Providers;

use App\Repositories\Impl\TransferEventRepo;
use App\Repositories\ITransferEventRepo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ITransferEventRepo::class, TransferEventRepo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
