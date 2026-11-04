<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\EventoWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_verificacion_del_webhook(): void
    {
        config(['whatsapp.verify_token' => 'secreto']);

        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=secreto&hub.challenge=123')->assertOk()->assertSeeText('123');
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=123')->assertForbidden();
    }

    public function test_un_mensaje_crea_a_la_clienta_sin_consentimiento_y_los_reintentos_no_duplican(): void
    {
        $payload = ['entry' => [['changes' => [['value' => [
            'contacts' => [['profile' => ['name' => 'Ana']]],
            'messages' => [['id' => 'wamid.1', 'from' => '5213312345678', 'type' => 'text', 'text' => ['body' => 'Hola']]],
        ]]]]]];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertSame(1, Cliente::count());
        $this->assertSame(1, EventoWhatsapp::count());
        $this->assertDatabaseHas('clientes', ['wa_id' => '5213312345678', 'nombre' => 'Ana', 'acepta_avisos' => false]);
        $this->assertNotNull(EventoWhatsapp::first()->cliente_id);
    }
}
