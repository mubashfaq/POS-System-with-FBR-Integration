<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Product;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        View::composer('*', function ($view) {
            $currentBusinessId = session('business_id');
            $view->with('currentBusinessId', $currentBusinessId);
        });

        Blade::anonymousComponentPath(
            resource_path('views/backend/components'),
            'backend'   // → <x-backend.modal>
        );
    }
}
