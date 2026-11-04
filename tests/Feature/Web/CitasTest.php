<?php

namespace Tests\Feature\Web;

use App\Models\Cita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class CitasTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();
    }

    public function test_una_clienta_agenda_su_cita_y_queda_guardada(): void
    {
        $clienta = $this->clienta();
        $corte = $this->servicioSimple();

        $this->como($clienta)->postJson('/api/web/citas', ['servicio_id' => $corte->id, 'inicio' => '2026-11-10 10:00', 'notas' => 'Corto a los lados'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.origen', 'web')
            ->assertJsonPath('data.inicio', '2026-11-10T10:00:00-06:00')
            ->assertJsonPath('data.fin', '2026-11-10T11:00:00-06:00');

        $this->assertDatabaseHas('citas', [
            'cliente_id' => $clienta->cliente->id, 'servicio_id' => $corte->id,
            'inicio' => '2026-11-10 10:00:00', 'fin' => '2026-11-10 11:00:00', 'estado' => 'confirmada',
        ]);
        $this->assertDatabaseCount('cita_bloques', 1);
    }

    /** El ejemplo del martes: tinte 9:00-12:00 con dos cortes de hombre durante el procesamiento. */
    public function test_ejemplo_del_martes_tinte_y_dos_cortes_traslapados_compatibles(): void
    {
        $admin = $this->admin();
        $tinte = $this->tinte();
        $corte = $this->servicioSimple();

        $this->agendarComoAdmin($admin, $tinte, $this->clienta()->cliente, '2026-11-10 09:00')
            ->assertCreated()->assertJsonPath('data.fin', '2026-11-10T12:00:00-06:00')->assertJsonCount(3, 'data.bloques');
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:30')->assertCreated();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 10:30')->assertCreated();

        $this->assertSame(3, Cita::where('estado', 'confirmada')->count());
    }

    public function test_dos_cortes_al_mismo_tiempo_son_incompatibles_y_se_ofrecen_alternativas(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:30')
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'estilista_ocupada')
            ->assertJsonPath('alternativas.0.inicio', '2026-11-10T10:00:00-06:00');

        $this->assertSame(1, Cita::count());
    }

    public function test_un_corte_que_choca_con_una_etapa_activa_del_tinte_se_rechaza(): void
    {
        $admin = $this->admin();
        $this->agendarComoAdmin($admin, $this->tinte(), $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        $this->agendarComoAdmin($admin, $this->servicioSimple(), $this->clienta()->cliente, '2026-11-10 11:00')
            ->assertStatus(409)->assertJsonPath('motivo', 'estilista_ocupada');
    }

    public function test_dos_tintes_solo_se_traslapan_si_sus_etapas_activas_no_chocan(): void
    {
        $admin = $this->admin();
        $tinte = $this->tinte();
        $this->agendarComoAdmin($admin, $tinte, $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        // Su aplicación (09:15-09:45) choca con la del primero (09:00-09:30).
        $this->agendarComoAdmin($admin, $tinte, $this->clienta()->cliente, '2026-11-10 09:15')->assertStatus(409);

        // Aplicación 09:30-10:00 y enjuague 12:00-12:30: no chocan con 09:00-09:30 ni con 11:30-12:00.
        $this->agendarComoAdmin($admin, $tinte, $this->clienta()->cliente, '2026-11-10 09:30')->assertCreated();
    }

    public function test_rechaza_horarios_fuera_de_atencion_dias_cerrados_y_el_pasado(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $cliente = $this->clienta()->cliente;

        $this->agendarComoAdmin($admin, $corte, $cliente, '2026-11-10 18:30')->assertStatus(409)->assertJsonPath('motivo', 'fuera_de_horario');
        $this->agendarComoAdmin($admin, $corte, $cliente, '2026-11-07 10:00')->assertStatus(409)->assertJsonPath('motivo', 'en_el_pasado');
        $this->agendarComoAdmin($admin, $corte, $cliente, '2026-11-16 10:00')->assertStatus(409)->assertJsonPath('motivo', 'dia_sin_atencion');
    }

    public function test_respeta_el_maximo_configurable_de_clientas_simultaneas(): void
    {
        config(['agenda.max_clientas_simultaneas' => 1]);
        $admin = $this->admin();
        $this->agendarComoAdmin($admin, $this->tinte(), $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        $this->agendarComoAdmin($admin, $this->servicioSimple(), $this->clienta()->cliente, '2026-11-10 09:30')
            ->assertStatus(409)->assertJsonPath('motivo', 'capacidad_llena');
    }

    public function test_validaciones_de_cita(): void
    {
        $corte = $this->servicioSimple();

        $this->como($this->clienta())->postJson('/api/web/citas', ['servicio_id' => $corte->id, 'inicio' => '2026-11-10 10:00', 'cliente_id' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['cliente_id']);
        $this->postJson('/api/web/citas', ['servicio_id' => 999, 'inicio' => 'mañana'])
            ->assertUnprocessable()->assertJsonValidationErrors(['servicio_id', 'inicio']);

        $this->como($this->admin())->postJson('/api/web/citas', ['servicio_id' => $corte->id, 'inicio' => '2026-11-10 10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_una_clienta_solo_ve_sus_citas(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $ana = $this->clienta();
        $rocio = $this->clienta();

        $mia = $this->agendarComoAdmin($admin, $corte, $ana->cliente, '2026-11-10 09:00')->json('data.id');
        $ajena = $this->agendarComoAdmin($admin, $corte, $rocio->cliente, '2026-11-10 11:00')->json('data.id');

        $this->como($ana)->getJson('/api/web/citas')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mia);
        $this->getJson("/api/web/citas/{$ajena}")->assertForbidden();
        $this->getJson("/api/web/citas/{$mia}")->assertOk();
    }

    public function test_cancelar_libera_el_horario_registra_el_hueco_y_es_idempotente(): void
    {
        $ana = $this->clienta();
        $corte = $this->servicioSimple();
        $id = $this->como($ana)->postJson('/api/web/citas', ['servicio_id' => $corte->id, 'inicio' => '2026-11-10 09:00'])->json('data.id');

        $this->postJson("/api/web/citas/{$id}/cancelar")->assertOk()->assertJsonPath('data.estado', 'cancelada');
        $this->postJson("/api/web/citas/{$id}/cancelar")->assertOk();

        $this->assertDatabaseHas('citas', ['id' => $id, 'estado' => 'cancelada']);
        $this->assertDatabaseCount('espacios_liberados', 1);
        $this->assertDatabaseHas('espacios_liberados', ['cita_id' => $id, 'motivo' => 'cancelacion', 'inicio' => '2026-11-10 09:00:00']);

        $slots = $this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10")->json('slots');
        $this->assertSame('2026-11-10T09:00:00-06:00', $slots[0]['inicio']);
    }

    public function test_una_clienta_no_cancela_citas_ajenas_ni_citas_que_ya_empezaron(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $ana = $this->clienta();
        $ajena = $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 11:00')->json('data.id');
        $mia = $this->agendarComoAdmin($admin, $corte, $ana->cliente, '2026-11-10 09:00')->json('data.id');

        $this->como($ana)->postJson("/api/web/citas/{$ajena}/cancelar")->assertForbidden();

        $this->travelTo(Carbon::parse('2026-11-10 09:10'));
        $this->postJson("/api/web/citas/{$mia}/cancelar")->assertUnprocessable();
        $this->assertDatabaseHas('citas', ['id' => $mia, 'estado' => 'confirmada']);
    }

    public function test_reprogramar_mueve_la_cita_y_libera_el_horario_anterior(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $id = $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 09:00')->json('data.id');
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 15:00')->assertCreated();

        $this->patchJson("/api/web/citas/{$id}", ['inicio' => '2026-11-10 13:00'])
            ->assertOk()->assertJsonPath('data.inicio', '2026-11-10T13:00:00-06:00');

        $this->assertDatabaseHas('citas', ['id' => $id, 'inicio' => '2026-11-10 13:00:00', 'fin' => '2026-11-10 14:00:00']);
        $this->assertDatabaseHas('cita_bloques', ['cita_id' => $id, 'inicio' => '2026-11-10 13:00:00']);
        $this->assertDatabaseHas('espacios_liberados', ['cita_id' => $id, 'motivo' => 'reprogramacion', 'inicio' => '2026-11-10 09:00:00']);

        $this->patchJson("/api/web/citas/{$id}", ['inicio' => '2026-11-10 15:30'])->assertStatus(409);
        $this->assertDatabaseHas('citas', ['id' => $id, 'inicio' => '2026-11-10 13:00:00']);
    }

    public function test_la_duena_registra_la_visita_y_una_cita_completada_no_se_cancela(): void
    {
        $admin = $this->admin();
        $id = $this->agendarComoAdmin($admin, $this->servicioSimple(), $this->clienta()->cliente, '2026-11-10 09:00')->json('data.id');

        $this->patchJson("/api/web/admin/citas/{$id}/estado", ['estado' => 'completada'])->assertUnprocessable();

        $this->travelTo(Carbon::parse('2026-11-10 10:05'));
        $this->patchJson("/api/web/admin/citas/{$id}/estado", ['estado' => 'completada'])
            ->assertOk()->assertJsonPath('data.estado', 'completada');
        $this->postJson("/api/web/citas/{$id}/cancelar")->assertUnprocessable();
        $this->assertDatabaseHas('citas', ['id' => $id, 'estado' => 'completada']);
    }
}
