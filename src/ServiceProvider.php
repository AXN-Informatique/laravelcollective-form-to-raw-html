<?php

namespace Axn\LaravelCollectiveFormToRawHtml;

use Axn\LaravelCollectiveFormToRawHtml\Console\RunCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.laravelcollective-form-to-raw-html.run', function () {
            return new RunCommand();
        });

        $this->commands([
            'command.laravelcollective-form-to-raw-html.run',
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.laravelcollective-form-to-raw-html.run',
        ];
    }
}
