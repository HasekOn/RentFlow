<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | CORS je vypnutý: React SPA na jiném originu už není a nové UI (Blade + Livewire)
    | běží na stejné doméně. API /api/v1 se používá s Bearer tokeny. Jestli API zůstane
    | a pro koho, se rozhodne ve fázi 6 (docs/REWRITE_PLAN.md) – až pak nastavit originy.
    |
    | Soubor nemazat: bez něj by platila výchozí konfigurace Laravelu s originem '*'.
    |
    */

    'paths' => [],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
