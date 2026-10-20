<?php

return [
    'api_version'  => env('WHATSAPP_API_VERSION', 'v25.0'),
    'phone_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id'      => env('WHATSAPP_WABA_ID', env('WHATSAPP_BUSINESS_ACCOUNT_ID')),
    'access_token' => env('WHATSAPP_TOKEN', env('WHATSAPP_ACCESS_TOKEN')),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    // Secreto de la app de Meta. Con él se valida la firma X-Hub-Signature-256
    // de cada POST al webhook; sin él, cualquiera podría inventar mensajes.
    'app_secret'   => env('WHATSAPP_APP_SECRET'),

    // Apagado, el webhook registra los mensajes pero el bot no contesta.
    'bot_activo'   => (bool) env('WHATSAPP_BOT_ACTIVO', true),

    // Costo aproximado por plantilla de marketing entregada en México (USD),
    // para que la dueña vea cuánto cuesta una difusión antes de enviarla.
    'costo_marketing_usd' => (float) env('WHATSAPP_COSTO_MARKETING_USD', 0.0436),

    'negocio'      => [
        'nombre'    => env('NEGOCIO_NOMBRE', 'TuNegocio Citas'),
        'direccion' => env('NEGOCIO_DIRECCION', ''),
    ],

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
            'name'     => 'confirmacion_cita',
            'language' => 'es_MX',
            'category' => 'UTILITY',
            'body'     => 'Listo {{1}}, tu cita de {{2}} quedó confirmada para el {{3}} a las {{4}}. Te esperamos.',
            'ejemplos' => ['Fernanda', 'Corte', 'viernes 17 de octubre', '16:00'],
        ],

        'recordatorio_cita' => [
            'name'     => 'recordatorio_cita',
            'language' => 'es_MX',
            'category' => 'UTILITY',
            'body'     => 'Hola {{1}}, te recordamos tu cita de {{2}} mañana {{3}} a las {{4}}. Si necesitas moverla, respóndenos por aquí.',
            'ejemplos' => ['Fernanda', 'Corte', 'viernes 17 de octubre', '16:00'],
        ],

        'espacio_liberado' => [
            'name'     => 'espacio_liberado',
            'language' => 'es_MX',
            'category' => 'MARKETING',
            'body'     => 'Hola {{1}}, se liberó un espacio para {{2}} el {{3}} a las {{4}}. Si te interesa, respóndenos por aquí y lo apartamos.',
            'ejemplos' => ['Fernanda', 'Peinado', 'viernes 17 de octubre', '16:00'],
        ],

        // La dueña canceló desde el panel: hay que avisar aunque la ventana
        // de 24 h ya esté cerrada, y eso solo se puede con plantilla.
        'cita_cancelada' => [
            'name'     => 'cita_cancelada',
            'language' => 'es_MX',
            'category' => 'UTILITY',
            'body'     => 'Hola {{1}}, tu cita de {{2}} del {{3}} a las {{4}} fue cancelada. Escríbenos por aquí para elegir otro horario.',
            'ejemplos' => ['Fernanda', 'Corte', 'viernes 17 de octubre', '16:00'],
        ],

    ],
];
