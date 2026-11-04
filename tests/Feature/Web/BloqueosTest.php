<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class BloqueosTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();
    }

    public function test_un_bloqueo_impide_agendar_y_quita_horarios(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();

        $this->como($admin)->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-10 13:00', 'fin' => '2026-11-10 15:00', 'motivo' => 'Curso'])
            ->assertCreated()->assertJsonPath('data.motivo', 'Curso')->assertJsonCount(0, 'citas_afectadas');
        $this->assertDatabaseHas('bloqueos_agenda', ['inicio' => '2026-11-10 13:00:00', 'fin' => '2026-11-10 15:00:00']);

        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 13:30')
            ->assertStatus(409)->assertJsonPath('motivo', 'bloqueado');
        // Un tinte 11:00-14:00 tampoco cabe: la clienta seguiría en el local durante el bloqueo.
        $this->agendarComoAdmin($admin, $this->tinte(), $this->clienta()->cliente, '2026-11-10 11:00')->assertStatus(409);

        $inicios = collect($this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10")->json('slots'))
            ->map(fn ($s) => substr($s['inicio'], 11, 5));
        $this->assertTrue($inicios->contains('12:00'));
        $this->assertFalse($inicios->contains('12:30'));
        $this->assertFalse($inicios->contains('14:45'));
        $this->assertTrue($inicios->contains('15:00'));
    }

    public function test_el_bloqueo_avisa_de_citas_afectadas_sin_cancelarlas(): void
    {
        $admin = $this->admin();
        $id = $this->agendarComoAdmin($admin, $this->servicioSimple(), $this->clienta()->cliente, '2026-11-10 14:00')->json('data.id');

        $this->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-10 13:00', 'fin' => '2026-11-10 15:00'])
            ->assertCreated()->assertJsonCount(1, 'citas_afectadas')->assertJsonPath('citas_afectadas.0.id', $id);
        $this->assertDatabaseHas('citas', ['id' => $id, 'estado' => 'confirmada']);
    }

    public function test_eliminar_el_bloqueo_libera_la_agenda_y_se_puede_bloquear_un_dia_completo(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();

        $bloqueo = $this->como($admin)->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-10 13:00', 'fin' => '2026-11-10 15:00'])->json('data.id');
        $this->deleteJson("/api/web/admin/bloqueos/{$bloqueo}")->assertOk();
        $this->assertDatabaseCount('bloqueos_agenda', 0);
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 13:30')->assertCreated();

        $this->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-11 00:00', 'fin' => '2026-11-12 00:00', 'motivo' => 'Vacaciones'])->assertCreated();
        $this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-11")->assertOk()->assertJsonCount(0, 'slots');
        $this->getJson('/api/web/admin/bloqueos')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_valida_el_bloqueo(): void
    {
        $this->como($this->admin())->postJson('/api/web/admin/bloqueos', ['inicio' => '2026-11-10 15:00', 'fin' => '2026-11-10 13:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fin']);
    }
}
