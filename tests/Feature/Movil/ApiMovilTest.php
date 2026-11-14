<?php

namespace Tests\Feature\Movil;

use App\Models\Cita;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class ApiMovilTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();
    }

    public function test_una_clienta_se_registra_inicia_sesion_y_consulta_su_perfil(): void
    {
        $registro = $this->postJson('/api/movil/auth/registro', [
            'nombre' => 'Laura Gómez',
            'email' => 'laura.movil@ejemplo.com',
            'telefono' => '4491234567',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'dispositivo' => 'Pixel 8 Android',
        ]);

        $registro->assertCreated()
            ->assertJsonPath('tipo', 'Bearer')
            ->assertJsonPath('usuario.email', 'laura.movil@ejemplo.com')
            ->assertJsonStructure(['token', 'tipo', 'usuario']);

        $token = $registro->json('token');

        $this->withToken($token)
            ->getJson('/api/movil/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'laura.movil@ejemplo.com');

        $this->withToken($token)
            ->postJson('/api/movil/auth/logout')
            ->assertOk();

        $this->postJson('/api/movil/auth/login', [
            'email' => 'laura.movil@ejemplo.com',
            'password' => 'secreto123',
            'dispositivo' => 'Pixel 8 Android',
        ])->assertOk()->assertJsonStructure(['token', 'tipo', 'usuario']);
    }

    public function test_catalogo_de_servicios_solo_muestra_activos_y_consulta_horarios(): void
    {
        $activo = $this->servicioSimple();
        $inactivo = Servicio::create([
            'nombre' => 'Servicio Oculto',
            'precio' => 200,
            'activo' => false,
            'duracion_minutos' => 30,
        ]);

        $this->getJson('/api/movil/servicios')
            ->assertOk()
            ->assertJsonFragment(['id' => $activo->id])
            ->assertJsonMissing(['id' => $inactivo->id]);

        $this->getJson("/api/movil/servicios/{$activo->id}")->assertOk();
        $this->getJson("/api/movil/servicios/{$inactivo->id}")->assertNotFound();

        $this->getJson("/api/movil/disponibilidad?servicio_id={$activo->id}&fecha=2026-11-10")
            ->assertOk()
            ->assertJsonStructure(['slots']);

        $this->getJson("/api/movil/slots?servicio_id={$activo->id}&fecha=2026-11-10")
            ->assertOk()
            ->assertJsonStructure(['servicio_id', 'fecha', 'horarios']);
    }

    public function test_clienta_agenda_reprograma_y_cancela_su_cita_desde_movil(): void
    {
        $clienta = $this->clienta();
        $corte = $this->servicioSimple();

        // 1. Agendar cita
        $creada = $this->como($clienta)->postJson('/api/movil/citas', [
            'servicio_id' => $corte->id,
            'inicio' => '2026-11-10 10:00',
            'notas' => 'Reserva desde app Android',
        ]);

        $creada->assertCreated()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.inicio', '2026-11-10T10:00:00-06:00');

        $citaId = $creada->json('data.id');

        // 2. Conflicto en el mismo horario devuelve 409 con alternativas
        $this->como($this->clienta())->postJson('/api/movil/citas', [
            'servicio_id' => $corte->id,
            'inicio' => '2026-11-10 10:00',
        ])->assertStatus(409)->assertJsonStructure(['message', 'motivo', 'alternativas']);

        // 3. Reprogramar cita
        $this->como($clienta)->patchJson("/api/movil/citas/{$citaId}", [
            'inicio' => '2026-11-10 13:00',
        ])->assertOk()->assertJsonPath('data.inicio', '2026-11-10T13:00:00-06:00');

        // 4. Cancelar cita
        $this->como($clienta)->postJson("/api/movil/citas/{$citaId}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');

        $this->assertDatabaseHas('citas', ['id' => $citaId, 'estado' => 'cancelada']);
    }

    public function test_clienta_no_puede_ver_citas_ajenas_ni_admin_puede_usar_rutas_de_clienta(): void
    {
        $ana = $this->clienta();
        $rocio = $this->clienta();
        $admin = $this->admin();
        $corte = $this->servicioSimple();

        $citaAna = $this->como($ana)->postJson('/api/movil/citas', [
            'servicio_id' => $corte->id,
            'inicio' => '2026-11-10 09:00',
        ])->json('data.id');

        // Rocío no puede ver ni cancelar la cita de Ana
        $this->como($rocio)->getJson("/api/movil/citas/{$citaAna}")->assertForbidden();
        $this->como($rocio)->postJson("/api/movil/citas/{$citaAna}/cancelar")->assertForbidden();

        // Admin no tiene rol 'cliente' para rutas exclusivas de clientas móviles
        $this->como($admin)->getJson('/api/movil/citas')->assertForbidden();
    }
}