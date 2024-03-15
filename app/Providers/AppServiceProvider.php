<?php

namespace App\Providers;

use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\WooCommerceApiRepositoryInterface;
use App\Repositories\OrderRepository;
use App\Repositories\WooCommerceApiRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(WooCommerceApiRepositoryInterface::class, WooCommerceApiRepository::class);
    }
}
