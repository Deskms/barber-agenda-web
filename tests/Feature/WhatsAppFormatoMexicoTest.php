<?php

namespace Tests\Feature;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** El número de prueba de Meta a veces solo acepta 52 + 10 dígitos aunque el webhook diga 521. */
class WhatsAppFormatoMexicoTest extends TestCase
{
    public function test_si_meta_rechaza_521_reintenta_con_52(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => '(#131030) Recipient phone number not in allowed list', 'code' => 131030]], 400)
                ->push(['messages' => [['id' => 'wamid.ok']]], 200),
        ]);

        $respuesta = app(WhatsAppService::class)->enviarTexto('5213310000000', 'Hola');

        $this->assertTrue($respuesta->successful());
        $destinos = array_column($this->enviados(), 'to');
        $this->assertSame(['5213310000000', '523310000000'], $destinos);
    }

    public function test_otros_errores_no_se_reintentan(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 401),
        ]);

        app(WhatsAppService::class)->enviarTexto('5213310000000', 'Hola');

        $this->assertCount(1, $this->enviados());
    }
}
