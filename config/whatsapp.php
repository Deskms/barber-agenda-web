<?php

return [
    'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),
    'phone_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    /*
    |---------------------------------------------------------------------------
    | Catálogo de plantillas
    |---------------------------------------------------------------------------
    |
    | Esto es el código fuente de las plantillas. Lo que vive en Meta es una
    | copia aprobada de lo que está aquí, no al revés. Si cambio un texto,
    | cambio este archivo y vuelvo a mandar la plantilla a revisión.
    |
    | Categorías:
    |   UTILITY   → se dispara por algo que la clienta hizo (confirmar, recordar).
    |               Más barata y Meta la aprueba con menos fricción.
    |   MARKETING → nosotros iniciamos sin que ella haya pedido nada (ofrecer un
    |               espacio libre). Exige consentimiento y cuesta más.
    |
    */
    'plantillas' => [

        'confirmacion_cita' => [
            'name' => 'confirmacion_cita',
            'language' => 'es_MX',
            'category' => 'UTILITY',
            'body' => 'Listo {{1}}, tu cita de {{2}} quedó confirmada para el {{3}} a las {{4}}. Te esperamos.',
            'ejemplos' => ['Fernanda', 'Corte', 'viernes 17 de octubre', '16:00'],
        ],

        'recordatorio_cita' => [
            'name' => 'recordatorio_cita',
            'language' => 'es_MX',
            'category' => 'UTILITY',
            'body' => 'Hola {{1}}, te recordamos tu cita de {{2}} mañana {{3}} a las {{4}}. Si necesitas moverla, respóndenos por aquí.',
            'ejemplos' => ['Fernanda', 'Corte', 'viernes 17 de octubre', '16:00'],
        ],

        'espacio_liberado' => [
            'name' => 'espacio_liberado',
            'language' => 'es_MX',
            'category' => 'MARKETING',
            'body' => 'Hola {{1}}, se liberó un espacio para {{2}} el {{3}} a las {{4}}. Si te interesa, respóndenos por aquí y lo apartamos.',
            'ejemplos' => ['Fernanda', 'Peinado', 'viernes 17 de octubre', '16:00'],
        ],

    ],
];
