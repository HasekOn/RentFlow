<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Markdown;
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

        // texty od uživatelů (adresa, název tiketu) v e-mailech jako text, ne Markdown s odkazy (S4)
        Markdown::withSecuredEncoding();
    }
}
