<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
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
        Carbon::setLocale('cs');

        // Mimo produkci hned chyba při lazy loadingu, přiřazení nefillable atributu nebo čtení chybějícího atributu.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
