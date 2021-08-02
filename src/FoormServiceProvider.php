<?php

namespace Gecche\Foorm;

use Gecche\Breeze\Console\CompileRelationsCommand;
use Gecche\Breeze\Database\Console\Migrations\MigrateMakeCommand;
use Gecche\Cupparis\Datafile\DatafileManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as DBBuilder;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class FoormServiceProvider extends ServiceProvider
{


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('foorm', function($app)
        {
            return new FoormManager($app['config']->get('foorm'));
        });
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {

        $this->publishes([
            __DIR__.'/config/foorm.php' => config_path('foorm.php'),
        ]);

    }

}
