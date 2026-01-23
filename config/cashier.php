<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The "publishable" key is typically used when interacting with
    | Stripe.js while the "secret" key accesses private API endpoints.
    |
    */

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used by Cashier to represent your users.
    | You may change this to any model you wish, but it must use the
    | Billable trait provided by the Laravel Cashier package.
    |
    */

    'model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Cashier can handle certain Stripe webhooks automatically. However, 
    | since we are using a custom controller, we define it here.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'controller' => \App\Http\Controllers\CustomWebhookController::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating
    | invoices. You may change this to any currency you wish.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'gbp'),

    'currency_symbol' => env('CASHIER_CURRENCY_SYMBOL', '£'),

];
