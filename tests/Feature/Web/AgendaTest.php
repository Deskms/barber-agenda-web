<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();
    }

    public function test_agenda_del_dia_y_de_la_semana(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-11 09:00')->assertCreated();
        $this->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-10 14:00', 'fin' => '2026-11-10 15:00'])->assertCreated();

        $this->getJson('/api/web/admin/agenda?fecha=2026-11-10')->assertOk()
            ->assertJsonCount(1, 'citas')->assertJsonCount(1, 'bloqueos')->assertJsonCount(1, 'citas.0.bloques');

        $this->getJson('/api/web/admin/agenda?fecha=2026-11-12&vista=semana')->assertOk()
            ->assertJsonPath('desde', '2026-11-09T00:00:00-06:00')->assertJsonCount(2, 'citas');
    }

    public function test_cambios_devuelve_solo_lo_modificado_desde_la_ultima_consulta(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        $marca = $this->getJson('/api/web/admin/agenda/cambios?desde=2026-11-09T07:00:00-06:00')
            ->assertOk()->assertJsonCount(1, 'citas')->json('servidor_ahora');

        $this->travelTo(Carbon::parse('2026-11-09 08:05'));
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 11:00')->assertCreated();

        $this->getJson('/api/web/admin/agenda/cambios?desde='.urlencode($marca))->assertOk()
            ->assertJsonCount(1, 'citas')->assertJsonPath('citas.0.inicio', '2026-11-10T11:00:00-06:00');
    }

    public function test_la_duena_configura_horarios_de_atencion(): void
    {
        $this->como($this->admin())->putJson('/api/web/admin/horarios/1', ['activo' => true, 'abre' => '10:00', 'cierra' => '14:00'])
            ->assertOk()->assertJsonPath('data.dia', 'lunes');
        $this->assertDatabaseHas('horarios_atencion', ['dia_semana' => 1, 'activo' => true]);

        $this->putJson('/api/web/admin/horarios/1', ['activo' => true, 'abre' => '14:00', 'cierra' => '10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cierra']);

        $this->getJson('/api/web/horarios')->assertOk()->assertJsonCount(7, 'data')->assertJsonPath('data.0.activo', false);
    }
}
