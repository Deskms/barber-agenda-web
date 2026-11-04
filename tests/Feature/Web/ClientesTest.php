<?php

namespace Tests\Feature\Web;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    public function test_la_duena_registra_y_edita_clientas_con_consentimiento(): void
    {
        $this->como($this->admin());

        $id = $this->postJson('/api/web/admin/clientes', ['nombre' => 'Lucía', 'telefono' => '(33) 1111-2222'])
            ->assertCreated()->assertJsonPath('data.telefono', '5213311112222')->assertJsonPath('data.acepta_avisos', false)->json('data.id');

        $this->postJson('/api/web/admin/clientes', ['nombre' => 'Otra', 'telefono' => '3311112222'])
            ->assertUnprocessable()->assertJsonValidationErrors(['telefono']);

        $this->patchJson("/api/web/admin/clientes/{$id}", ['acepta_avisos' => true])->assertOk()->assertJsonPath('data.acepta_avisos', true);
        $this->assertDatabaseHas('clientes', ['id' => $id, 'acepta_avisos' => true]);

        $this->getJson('/api/web/admin/clientes?buscar=Luc')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_historial_cuenta_las_visitas_completadas(): void
    {
        $this->prepararAgenda();
        $admin = $this->admin();
        $ana = $this->clienta();
        $id = $this->agendarComoAdmin($admin, $this->servicioSimple(), $ana->cliente, '2026-11-10 09:00')->json('data.id');

        $this->travelTo(Carbon::parse('2026-11-10 10:30'));
        $this->patchJson("/api/web/admin/citas/{$id}/estado", ['estado' => 'completada'])->assertOk();

        $this->getJson("/api/web/admin/clientes/{$ana->cliente->id}/historial")->assertOk()
            ->assertJsonPath('resumen.visitas', 1)->assertJsonPath('resumen.ultima_visita', '2026-11-10T09:00:00-06:00');

        $this->como($ana)->getJson('/api/web/mi/historial')->assertOk()->assertJsonPath('resumen.visitas', 1)->assertJsonCount(1, 'citas');
    }

    public function test_historial_de_conversacion_a_partir_del_webhook(): void
    {
        $cliente = Cliente::factory()->create(['wa_id' => '5213312345678']);

        $this->postJson('/api/whatsapp/webhook', [
            'entry' => [['changes' => [['value' => [
                'contacts' => [['profile' => ['name' => 'Ana']]],
                'messages' => [['id' => 'wamid.ABC', 'from' => '5213312345678', 'type' => 'text', 'text' => ['body' => 'Hola, ¿tienen lugar el sábado?']]],
            ]]]]],
        ])->assertOk();

        $this->como($this->admin())->getJson("/api/web/admin/clientes/{$cliente->id}/mensajes")->assertOk()
            ->assertJsonCount(1, 'mensajes')
            ->assertJsonPath('mensajes.0.direccion', 'entrante')
            ->assertJsonPath('mensajes.0.texto', 'Hola, ¿tienen lugar el sábado?');
    }
}
