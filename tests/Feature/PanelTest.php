<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\User;
use App\Services\Agenda\CitaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PanelTest extends TestCase
{
    private function duena(): User
    {
        return User::factory()->create();
    }

    public function test_todo_el_panel_exige_sesion(): void
    {
        foreach (['/', '/semana', '/citas/nueva', '/clientas', '/servicios', '/horarios', '/huecos', '/whatsapp', '/agenda/feed'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }

        $this->post('/citas', [])->assertRedirect('/login');
        $this->get('/privacidad')->assertOk();
    }

    public function test_login_y_vistas_principales(): void
    {
        $user = User::factory()->create(['password' => 'clave-segura-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'mala'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'clave-segura-123'])->assertRedirect('/');

        $this->fingirMeta();
        app(CitaService::class)->agendar($this->clienta(), $this->corte(), Carbon::parse('2026-10-20 12:00'));

        $this->get('/')->assertOk()->assertSee('Ana')->assertSee('Libre hasta las 12:00');
        foreach (['/semana', '/citas/nueva', '/clientas', '/servicios', '/horarios', '/huecos', '/clientas/' . $this->clienta()->id] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_crear_cita_desde_el_panel_registra_clienta_nueva(): void
    {
        $this->actingAs($this->duena());

        $this->getJson('/citas/disponibilidad?servicio_id=' . $this->corte()->id . '&fecha=2026-10-20')
            ->assertOk()->assertJsonPath('horarios.0.valor', '10:00');

        $this->post('/citas', [
            'servicio_id' => $this->corte()->id, 'fecha' => '2026-10-20', 'hora' => '10:00',
            'nombre' => 'Lupita', 'telefono' => '33 1111 2222',
        ])->assertRedirect();

        $this->assertSame('5213311112222', Cliente::where('nombre', 'Lupita')->value('wa_id'));
        $this->assertSame(Cita::ORIGEN_PANEL, Cita::sole()->origen);

        // El mismo horario ya no se puede tomar.
        $this->post('/citas', [
            'servicio_id' => $this->corte()->id, 'fecha' => '2026-10-20', 'hora' => '10:30',
            'nombre' => 'Otra', 'telefono' => '3322223333',
        ])->assertSessionHasErrors('hora');
        $this->assertSame(1, Cita::count());
    }

    public function test_desactivar_servicio_no_toca_citas_existentes(): void
    {
        $this->actingAs($this->duena());
        $corte = $this->corte();
        $cita  = app(CitaService::class)->agendar($this->clienta(), $corte, Carbon::parse('2026-10-20 12:00'));

        $this->put("/servicios/{$corte->id}", ['nombre' => $corte->nombre, 'duracion_minutos' => 60])->assertRedirect();

        $this->assertFalse($corte->fresh()->activo);
        $this->assertSame(Cita::CONFIRMADA, $cita->fresh()->estado);
        $this->getJson("/citas/disponibilidad?servicio_id={$corte->id}&fecha=2026-10-21")->assertNotFound();
    }

    public function test_difusion_no_repite_destinatarias_ni_envia_a_no_elegibles(): void
    {
        $this->actingAs($this->duena());
        $this->fingirMeta();

        $cita = app(CitaService::class)->agendar($this->clienta('Ana'), $this->corte(), Carbon::parse('2026-10-20 12:00'));
        app(CitaService::class)->cancelar($cita);

        $rocio    = $this->clienta('Rocío');
        $fernanda = $this->clienta('Fernanda'); // sin consentimiento

        $this->post("/huecos/{$cita->id}/difundir", ['clientas' => [$rocio->id, $fernanda->id]])->assertRedirect();
        $this->post("/huecos/{$cita->id}/difundir", ['clientas' => [$rocio->id]])->assertRedirect();

        $this->assertSame(1, Difusion::where('cliente_id', $rocio->id)->count());
        $this->assertSame(0, Difusion::where('cliente_id', $fernanda->id)->count());
        $this->assertCount(1, $this->enviados());
        $this->assertSame('espacio_liberado', $this->enviados()[0]['template']['name']);
    }

    public function test_recordatorio_se_envia_una_sola_vez(): void
    {
        $this->fingirMeta();
        app(CitaService::class)->agendar($this->clienta(), $this->corte(), Carbon::parse('2026-10-21 12:00'));

        Artisan::call('citas:recordar');
        Artisan::call('citas:recordar');

        $this->assertCount(1, $this->enviados());
        $this->assertSame('recordatorio_cita', $this->enviados()[0]['template']['name']);
        $this->assertNotNull(Cita::sole()->recordatorio_enviado_en);
    }
}
