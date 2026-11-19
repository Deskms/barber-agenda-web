<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\Huecos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Apartado temporal, recuperación medible, ofertas en la app, faltas y reprogramación. */
class HuecosTest extends TestCase
{
    private const HUECO = '2026-10-22 16:00';

    private function citas(): CitaService
    {
        return app(CitaService::class);
    }

    /** Rocío cancela su corte del jueves y se le avisa a $clienta. */
    private function huecoAvisadoA(Cliente $clienta): Difusion
    {
        $this->fingirMeta();
        $cancelada = $this->citas()->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse(self::HUECO));
        $this->citas()->cancelar($cancelada);

        return Difusion::create([
            'cita_id'    => $cancelada->id,
            'cliente_id' => $clienta->id,
            'seccion_id' => $cancelada->seccion_id,
            'inicio'     => $cancelada->inicio,
            'fin'        => $cancelada->fin,
            'plantilla'  => 'texto',
            'gratis'     => true,
            'automatica' => true,
            'estado'     => Huecos::ENVIADA,
        ]);
    }

    private function comoClienta(Cliente $cliente): self
    {
        $token = $cliente->createToken('pruebas', ['movil'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Ana suele pedir "Corte y barba" (2 h), pero el hueco del jueves es de
     * 1 h porque Fernanda tiene cita a las 17:00: su servicio habitual no cabe.
     */
    private function huecoMasCortoQueSuHabitual(): array
    {
        $ana = $this->clienta('Ana');
        $largo = Servicio::create(['nombre' => 'Corte y barba', 'duracion_minutos' => 120, 'precio' => 250, 'seccion_id' => $this->corte()->seccion_id, 'activo' => true]);
        $antes = Carbon::parse('2026-10-10 12:00');
        Cita::create([
            'cliente_id' => $ana->id, 'servicio_id' => $largo->id, 'seccion_id' => $largo->seccion_id,
            'inicio' => $antes, 'fin' => $antes->copy()->addMinutes(120), 'estado' => Cita::COMPLETADA, 'origen' => Cita::ORIGEN_PANEL,
        ]);
        $this->citas()->agendar($this->clienta('Fernanda'), $this->corte(), Carbon::parse('2026-10-22 17:00'));

        return [$ana, $this->huecoAvisadoA($ana)];
    }

    private function postWebhook(Cliente $de, array $mensaje): void
    {
        $mensaje += ['from' => $de->wa_id, 'id' => 'wamid.' . Str::random(12), 'timestamp' => (string) now()->timestamp];
        $cuerpo = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['profile' => ['name' => $de->nombre], 'wa_id' => $de->wa_id]],
            'messages' => [$mensaje],
        ]]]]]]);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $cuerpo, 'secreto-de-prueba'),
        ], $cuerpo)->assertOk();
    }

    public function test_la_oferta_usa_el_servicio_avisado_si_el_habitual_no_cabe(): void
    {
        [$ana, $difusion] = $this->huecoMasCortoQueSuHabitual();

        $this->comoClienta($ana)->getJson('/api/movil/ofertas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $difusion->id)
            ->assertJsonPath('data.0.servicio.id', $this->corte()->id);

        $this->comoClienta($ana)->postJson("/api/movil/ofertas/{$difusion->id}/aceptar")
            ->assertStatus(201)
            ->assertJsonPath('data.servicio.id', $this->corte()->id);

        $this->assertSame($difusion->id, Cita::where('cliente_id', $ana->id)->where('inicio', self::HUECO . ':00')->value('difusion_id'));
    }

    public function test_escribir_agendar_despues_del_aviso_ofrece_ese_hueco(): void
    {
        [$ana, $difusion] = $this->huecoMasCortoQueSuHabitual();

        $this->postWebhook($ana, ['type' => 'text', 'text' => ['body' => 'Agendar']]);

        $ultimo = collect($this->enviados())->last();
        $botones = array_column(array_column($ultimo['interactive']['action']['buttons'], 'reply'), 'id');
        $this->assertSame("conf:{$this->corte()->id}:2026-10-22T16:00", $botones[0]);
        $this->assertNotNull($difusion->fresh()->apartado_hasta);
    }

    public function test_quien_aparta_el_hueco_lo_conserva_unos_minutos(): void
    {
        $ana = $this->clienta('Ana');
        $difusion = $this->huecoAvisadoA($ana);

        $this->assertTrue(app(Huecos::class)->apartar($difusion));

        // Otra clienta intenta tomar ese horario desde la app.
        $this->comoClienta($this->clienta('Fernanda'))
            ->postJson('/api/movil/citas', ['servicio_id' => $this->corte()->id, 'inicio' => '2026-10-22T16:00'])
            ->assertStatus(409)
            ->assertJsonPath('mensaje', 'Ese espacio está apartado unos minutos por otra persona.');

        // Quien lo apartó sí puede confirmarlo.
        $cita = $this->citas()->agendar($ana, $this->corte(), Carbon::parse(self::HUECO));
        $this->assertSame($ana->id, $cita->cliente_id);
    }

    public function test_el_apartado_vence_y_el_hueco_vuelve_a_estar_libre(): void
    {
        $difusion = $this->huecoAvisadoA($this->clienta('Ana'));
        app(Huecos::class)->apartar($difusion);

        Carbon::setTestNow(Carbon::parse(self::AHORA)->addMinutes(Huecos::MINUTOS_APARTADO + 1));

        $otra = Cliente::create(['wa_id' => '5213310009999', 'nombre' => 'Lupita']);
        $cita = $this->citas()->agendar($otra, $this->corte(), Carbon::parse(self::HUECO));
        $this->assertSame($otra->id, $cita->cliente_id);
    }

    public function test_la_cita_en_el_hueco_avisado_cuenta_como_recuperada(): void
    {
        $ana = $this->clienta('Ana');
        $difusion = $this->huecoAvisadoA($ana);

        $cita = $this->citas()->agendar($ana, $this->corte(), Carbon::parse(self::HUECO));

        $this->assertSame($difusion->id, $cita->fresh()->difusion_id);
        $this->assertSame(Huecos::ACEPTADA, $difusion->fresh()->estado);
    }

    public function test_la_app_muestra_y_acepta_ofertas_y_solo_una_gana(): void
    {
        $ana = $this->clienta('Ana');
        $difAna = $this->huecoAvisadoA($ana);
        $lupita = Cliente::create(['wa_id' => '5213310009999', 'nombre' => 'Lupita', 'acepta_avisos' => true]);
        $difLupita = Difusion::create($difAna->only(['cita_id', 'seccion_id', 'inicio', 'fin', 'plantilla', 'gratis', 'automatica', 'estado']) + ['cliente_id' => $lupita->id, 'inicio' => $difAna->inicio]);

        $this->comoClienta($ana)->getJson('/api/movil/ofertas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $difAna->id);

        $this->comoClienta($ana)->postJson("/api/movil/ofertas/{$difAna->id}/aceptar")->assertStatus(201);

        // Lupita llega tarde: el servidor no le da el mismo lugar.
        $this->comoClienta($lupita)->postJson("/api/movil/ofertas/{$difLupita->id}/aceptar")->assertStatus(409);
        $this->comoClienta($lupita)->getJson('/api/movil/ofertas')->assertOk()->assertJsonCount(0, 'data');

        // Nadie puede aceptar la oferta de otra.
        $this->comoClienta($lupita)->postJson("/api/movil/ofertas/{$difAna->id}/aceptar")->assertNotFound();

        $this->assertSame(1, Cita::where('inicio', self::HUECO . ':00')->vigentes()->count());
    }

    public function test_los_avisos_pasados_se_marcan_vencidos(): void
    {
        $difusion = $this->huecoAvisadoA($this->clienta('Ana'));
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00'));

        $this->artisan('huecos:vencer')->assertSuccessful();

        $this->assertSame(Huecos::VENCIDA, $difusion->fresh()->estado);
    }

    public function test_con_demasiadas_faltas_ya_no_agenda_sola_pero_la_duena_si(): void
    {
        Ajuste::guardar('faltas_bloqueo', '2');
        $ana = $this->clienta('Ana');

        foreach (['2026-10-01 12:00', '2026-10-08 12:00'] as $i => $cuando) {
            $inicio = Carbon::parse($cuando);
            Cita::create([
                'cliente_id' => $ana->id, 'servicio_id' => $this->corte()->id, 'seccion_id' => $this->corte()->seccion_id,
                'inicio' => $inicio, 'fin' => $inicio->copy()->addHour(), 'estado' => Cita::NO_ASISTIO, 'origen' => Cita::ORIGEN_PANEL,
            ]);
        }

        $this->comoClienta($ana)
            ->postJson('/api/movil/citas', ['servicio_id' => $this->corte()->id, 'inicio' => '2026-10-21T12:00'])
            ->assertStatus(409);

        $cita = $this->citas()->agendar($ana, $this->corte(), Carbon::parse('2026-10-21 12:00'), Cita::ORIGEN_PANEL);
        $this->assertSame(Cita::ORIGEN_PANEL, $cita->origen);
    }

    public function test_la_clienta_cambia_su_cita_de_horario_desde_la_app(): void
    {
        $this->fingirMeta();
        $ana  = $this->clienta('Ana');
        $cita = $this->citas()->agendar($ana, $this->corte(), Carbon::parse('2026-10-21 12:00'));

        // La disponibilidad no la tapa su propia cita.
        $this->comoClienta($ana)
            ->getJson("/api/movil/disponibilidad?servicio_id={$this->corte()->id}&fecha=2026-10-21&cita_id={$cita->id}")
            ->assertOk();

        $this->comoClienta($ana)
            ->postJson("/api/movil/citas/{$cita->id}/reprogramar", ['inicio' => '2026-10-21T15:00'])
            ->assertOk()
            ->assertJsonPath('data.inicio', '2026-10-21T15:00:00-06:00');

        // Una cita ajena no se puede mover.
        $ajena = $this->citas()->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse('2026-10-21 17:00'));
        $this->comoClienta($ana)
            ->postJson("/api/movil/citas/{$ajena->id}/reprogramar", ['inicio' => '2026-10-21T18:00'])
            ->assertNotFound();
    }

    public function test_reprogramar_no_encima_otra_cita_de_la_misma_clienta(): void
    {
        $ana = $this->clienta('Ana');
        $corte = $this->citas()->agendar($ana, $this->corte(), Carbon::parse('2026-10-21 12:00'));
        // Otra cita de Ana en una sección distinta, a las 15:00.
        $otraSeccion = \App\Models\Servicio::where('seccion_id', '!=', $this->corte()->seccion_id)->activos()->firstOrFail();
        $this->citas()->agendar($ana, $otraSeccion, Carbon::parse('2026-10-21 15:00'));

        $this->expectException(\App\Exceptions\HorarioNoDisponibleException::class);
        $this->citas()->reprogramar($corte->load('servicio'), Carbon::parse('2026-10-21 15:00'));
    }

    public function test_el_tablero_muestra_los_espacios_recuperados(): void
    {
        $ana = $this->clienta('Ana');
        $this->huecoAvisadoA($ana);
        $this->citas()->agendar($ana, $this->corte(), Carbon::parse(self::HUECO));

        $this->actingAs(User::factory()->create())
            ->get('/tablero?mes=2026-10')
            ->assertOk()
            ->assertSee('Espacios recuperados')
            ->assertViewHas('recuperacion', fn ($r) => $r['espacios'] === 1);
    }
}
