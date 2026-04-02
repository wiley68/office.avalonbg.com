<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Organization (tenant / client name in UI)
    |--------------------------------------------------------------------------
    |
    | Показва се в навигацията и други места като „кой ползва“ приложението.
    |
    */

    'organization' => env('APP_ORGANIZATION', ''),

    /**
     * URL на магазина
     */
    'shop_url' => env('APP_SHOP_URL', 'https://avalonbg.com'),

    /*
    |--------------------------------------------------------------------------
    | Шапка за печат на сервизна карта (издаване на продукт)
    |--------------------------------------------------------------------------
    */

    'service_card_letterhead' => [
        'company_name' => env('SERVICE_CARD_LETTERHEAD_COMPANY', 'Авалон ООД'),
        'logo_url' => env('SERVICE_CARD_LETTERHEAD_LOGO_URL'),
        'address_lines' => env('SERVICE_CARD_LETTERHEAD_ADDRESS_LINES', implode("\n", [
            'Горна Оряховица, ул. Патриарх Евтимий №27',
            'Тел./Факс: (0619) 22218 / (0619) 99929',
            'e-mail: home@avalonbg.com    www.avalonbg.com',
        ])),
        'website' => env('SERVICE_CARD_LETTERHEAD_WEBSITE', 'www.avalonbg.com'),
        'tagline' => env('SERVICE_CARD_LETTERHEAD_TAGLINE', 'Computer systems & Consulting service'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret key for office text crypto (non-admin tool)
    |--------------------------------------------------------------------------
    |
    | Използва се за криптиране/декриптиране на текст през менюто „Криптиране“.
    | Задайте дълга случайна стойност в .env (APP_SECRET_KEY).
    |
    */

    'secret_key' => env('APP_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
