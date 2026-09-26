<?php

namespace App\Providers;

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
        // Auto-generate Swagger API documentation if it does not exist yet
        $docsPath = storage_path('api-docs/api-docs.json');
        if (!file_exists($docsPath) && !app()->runningInConsole()) {
            try {
                \Illuminate\Support\Facades\Artisan::call('l5-swagger:generate');
            } catch (\Throwable $e) {
                // Silently skip if generating docs fails
            }
        }
        // Register Queue Worker Heartbeat for instant, zero-overhead worker health monitoring
        try {
            $touchHeartbeat = function () {
                $heartbeatFile = storage_path('framework/worker_heartbeat.json');
                @file_put_contents($heartbeatFile, json_encode([
                    'pid' => getmypid(),
                    'timestamp' => time(),
                ]), LOCK_EX);
            };

            \Illuminate\Support\Facades\Queue::looping($touchHeartbeat);
            \Illuminate\Support\Facades\Queue::before($touchHeartbeat);
            \Illuminate\Support\Facades\Queue::after($touchHeartbeat);
        } catch (\Throwable $e) {
            // Silently ignore if queue service is not available
        }
    }
}
