<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use WishKnish\KnishIO\Providers\Service;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register ()
    {
        $this->app->register( AuthServiceProvider::class );       // Auth Service
        $this->app->register( LighthouseServiceProvider::class ); // Lighthouse GraphQL
        $this->app->register( Service::class );                   // KnishIO Service
    }
}
