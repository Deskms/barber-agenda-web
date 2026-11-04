<?php

namespace Tests\Feature\Web;

use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class ServiciosTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    public function test_la_duena_crea_un_servicio_con_etapas_y_la_duracion_es_la_suma(): void
    {
        $this->como($this->admin());

        $respuesta = $this->postJson('/api/web/admin/servicios', [
            'nombre' => 'Tinte', 'precio' => 900,
            'etapas' => [
                ['nombre' => 'Aplicación', 'duracion_minutos' => 30, 'ocupa_estilista' => true],
                ['nombre' => 'Procesamiento', 'duracion_minutos' => 120, 'ocupa_estilista' => false],
                ['nombre' => 'Enjuague', 'duracion_minutos' => 30, 'ocupa_estilista' => true],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.duracion_minutos', 180)
            ->assertJsonPath('data.etapas_configuradas', true)
            ->assertJsonPath('data.etapas.1.ocupa_estilista', false);

        $this->assertDatabaseHas('servicios', ['nombre' => 'Tinte', 'duracion_minutos' => 180]);
        $this->assertDatabaseCount('servicio_etapas', 3);
    }

    public function test_un_servicio_sin_etapas_tiene_una_sola_etapa_activa(): void
    {
        $this->como($this->admin());

        $this->postJson('/api/web/admin/servicios', ['nombre' => 'Manicura', 'duracion_minutos' => 45, 'precio' => 250])
            ->assertCreated()
            ->assertJsonPath('data.duracion_minutos', 45)
            ->assertJsonPath('data.etapas_configuradas', false)
            ->assertJsonPath('data.etapas.0.ocupa_estilista', true);
    }

    public function test_valida_los_datos_del_servicio(): void
    {
        $this->como($this->admin());

        $this->postJson('/api/web/admin/servicios', ['nombre' => 'Sin duración'])
            ->assertUnprocessable()->assertJsonValidationErrors(['duracion_minutos']);

        $this->postJson('/api/web/admin/servicios', [
            'nombre' => 'Solo espera',
            'etapas' => [['nombre' => 'Reposo', 'duracion_minutos' => 30, 'ocupa_estilista' => false]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['etapas']);

        $this->postJson('/api/web/admin/servicios', ['nombre' => 'Negativo', 'duracion_minutos' => 30, 'precio' => -5])
            ->assertUnprocessable()->assertJsonValidationErrors(['precio']);
    }

    public function test_editar_etapas_no_cambia_citas_ya_agendadas(): void
    {
        $this->prepararAgenda();
        $admin = $this->admin();
        $tinte = $this->tinte();
        $clienta = $this->clienta();

        $cita = $this->agendarComoAdmin($admin, $tinte, $clienta->cliente, '2026-11-10 09:00')->assertCreated();

        $this->putJson("/api/web/admin/servicios/{$tinte->id}", [
            'etapas' => [['nombre' => 'Todo junto', 'duracion_minutos' => 90, 'ocupa_estilista' => true]],
        ])->assertOk()->assertJsonPath('data.duracion_minutos', 90);

        $this->assertDatabaseCount('servicio_etapas', 1);
        $this->assertDatabaseHas('citas', ['id' => $cita->json('data.id'), 'fin' => '2026-11-10 12:00:00']);
        $this->assertDatabaseCount('cita_bloques', 3);
    }

    public function test_eliminar_desactiva_y_lo_retira_del_catalogo_publico(): void
    {
        $this->como($this->admin());
        $servicio = Servicio::factory()->create(['nombre' => 'Peinado']);

        $this->deleteJson("/api/web/admin/servicios/{$servicio->id}")->assertOk()->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('servicios', ['id' => $servicio->id, 'activo' => false]);
        $this->getJson('/api/web/servicios')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/web/servicios/{$servicio->id}")->assertNotFound();
        $this->getJson('/api/web/admin/servicios')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_catalogo_publico_muestra_servicios_activos(): void
    {
        $this->servicioSimple('Corte', 60);
        $this->tinte();

        $this->getJson('/api/web/servicios')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'nombre', 'duracion_minutos', 'precio', 'activo', 'etapas']]]);
    }
}
