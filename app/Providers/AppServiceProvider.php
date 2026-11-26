<?php

namespace App\Providers;

use App\Http\Order\Models\Order;
use App\Http\Order\Policies\OrderPolicy;
use App\Mail\Transports\RedactedLogTransport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        Gate::policy(Order::class, OrderPolicy::class);

        Mail::extend('redacted_log', fn (array $config): RedactedLogTransport => new RedactedLogTransport(
            Log::channel($config['channel'] ?? null),
        ));
    }
}
