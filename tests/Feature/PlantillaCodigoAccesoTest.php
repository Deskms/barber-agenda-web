<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Services\WhatsApp\PlantillaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlantillaCodigoAccesoTest extends TestCase
{
    public function test_la_plantilla_de_autenticacion_se_crea_con_el_formato_de_meta(): void
    {
        config(['whatsapp.waba_id' => '123', 'whatsapp.access_token' => 'x']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1', 'status' => 'PENDING'], 200)]);

        app(PlantillaService::class)->crear(config('whatsapp.plantillas.codigo_acceso'));

        $enviado = Http::recorded()[0][0]->data();
        $this->assertSame('AUTHENTICATION', $enviado['category']);
        $this->assertSame(600, $enviado['message_send_ttl_seconds']);
        $this->assertTrue($enviado['components'][0]['add_security_recommendation']);
        $this->assertArrayNotHasKey('text', $enviado['components'][0], 'Meta pone el texto del cuerpo');
        $this->assertSame(10, $enviado['components'][1]['code_expiration_minutes']);
        $this->assertSame('COPY_CODE', $enviado['components'][2]['buttons'][0]['otp_type']);
    }

    public function test_el_codigo_fuera_de_la_ventana_va_en_el_cuerpo_y_en_el_boton(): void
    {
        $this->fingirMeta();
        // Sin mensajes recientes: la ventana de 24 h está cerrada y solo se puede con plantilla.
        Cliente::create(['wa_id' => '5213339999999', 'acepta_avisos' => false]);

        $codigo = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3339999999'])->json('codigo_de_prueba');

        $mensaje = $this->enviados()[0];
        $this->assertSame('codigo_acceso', $mensaje['template']['name']);
        $this->assertSame($codigo, $mensaje['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame('button', $mensaje['template']['components'][1]['type']);
        $this->assertSame($codigo, $mensaje['template']['components'][1]['parameters'][0]['text']);
    }
}
