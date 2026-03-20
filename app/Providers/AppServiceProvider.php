<?php

namespace App\Providers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\User;
use App\Observers\RouterObserver;
use App\Observers\SubscriberObserver;
use App\Policies\RouterPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        URL::forceScheme('https');

        // Register policies
        Gate::policy(Router::class, RouterPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Register observers
        Router::observe(RouterObserver::class);
        Subscriber::observe(SubscriberObserver::class);

        // Register middleware alias
        app('router')->aliasMiddleware('router.access', \App\Http\Middleware\CheckRouterAccess::class);
    }
}
