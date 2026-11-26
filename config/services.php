<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    // Interpretación de texto libre en WhatsApp. Sin llave, el bot funciona
    // solo con botones y listas.
    // Inicio de sesión con Google en la app móvil. Es el "ID de cliente web"
    // de Google Cloud (el que la app pone como serverClientId). Se aceptan
    // varios separados por coma.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'anthropic' => [
        'key'    => env('ANTHROPIC_API_KEY'),
        'model'  => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'), // clasificar intención: Haiku basta y cuesta ~40 veces menos que Opus
        'effort' => env('ANTHROPIC_EFFORT', 'low'),
    ],

];
