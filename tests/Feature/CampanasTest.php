<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\Recontacto;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampanasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fingirMeta();
    }

    /** Cita ya pasada, creada directo porque el servicio de citas no deja agendar en el pasado. */
    private function visitaPasada(Cliente $c, Servicio $s, string $cuando): Cita
    {
        $inicio = Carbon::parse($cuando);

        return Cita::create([
            'cliente_id' => $c->id, 'servicio_id' => $s->id, 'seccion_id' => $s->seccion_id,
            'inicio' => $inicio, 'fin' => $inicio->copy()->addMinutes($s->duracion_minutos),
            'estado' => Cita::COMPLETADA, 'origen' => Cita::ORIGEN_PANEL,
        ]);
    }

    private function cliente(string $nombre, string $waId, bool $ventana = false): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'wa_id' => $waId, 'acepta_avisos' => true, 'ultimo_mensaje_en' => $ventana ? now()->subHours(2) : null]);
    }

    private function tinte(): Servicio
    {
        return Servicio::where('nombre', 'Tinte o decoloración')->firstOrFail();
    }

    /** Escenario: Luis (escribió hace 2 h) y Pedro se cortan el pelo; Ana solo se pinta. */
    private function escenario(): array
    {
        $luis  = $this->cliente('Luis', '5213310000001', ventana: true);
        $pedro = $this->cliente('Pedro', '5213310000002');
        $this->visitaPasada($luis, $this->corte(), '2026-10-10 12:00');
        $this->visitaPasada($pedro, $this->corte(), '2026-10-08 12:00');
        $this->visitaPasada($this->clienta('Ana'), $this->tinte(), '2026-10-09 12:00');

        // Rocío tenía cita de corte el jueves y la cancela.
        $cita = app(CitaService::class)->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse('2026-10-22 16:00'));

        return [$luis, $pedro, $cita];
    }

    private function aQuien(): array
    {
        return collect($this->enviados())->map(fn ($m) => $m['to'] . ':' . $m['type'])->all();
    }

    public function test_apagado_no_manda_nada_al_cancelar(): void
    {
        [, , $cita] = $this->escenario();

        app(CitaService::class)->cancelar($cita);

        $this->assertSame([], $this->enviados());
    }

    public function test_al_cancelar_avisa_solo_a_quienes_usan_esa_seccion_y_primero_gratis(): void
    {
        Ajuste::guardar('huecos_auto', '1');
        [$luis, $pedro, $cita] = $this->escenario();

        app(CitaService::class)->cancelar($cita);

        // Luis con ventana abierta: botones gratis. Pedro: plantilla pagada. Ana (solo tinte) y Rocío (canceló): nada.
        $this->assertSame(["{$luis->wa_id}:interactive", "{$pedro->wa_id}:template"], $this->aQuien());
        $this->assertTrue(Difusion::where('cliente_id', $luis->id)->value('gratis'));
        $this->assertFalse(Difusion::where('cliente_id', $pedro->id)->value('gratis'));
        $this->assertSame('espacio_liberado', $this->enviados()[1]['template']['name']);
    }

    public function test_respeta_tope_por_hueco_y_presupuesto(): void
    {
        Ajuste::guardar('huecos_auto', '1');
        Ajuste::guardar('presupuesto_mensual', '0');
        [$luis, , $cita] = $this->escenario();

        app(CitaService::class)->cancelar($cita);

        $this->assertSame(["{$luis->wa_id}:interactive"], $this->aQuien(), 'Sin presupuesto solo salen los gratis');
    }

    public function test_mover_una_cita_avisa_del_horario_que_dejo(): void
    {
        Ajuste::guardar('huecos_auto', '1');
        [$luis, , $cita] = $this->escenario();

        app(CitaService::class)->reprogramar($cita->load('servicio'), Carbon::parse('2026-10-23 12:00'));

        $this->assertSame('2026-10-22 16:00', Difusion::where('cliente_id', $luis->id)->first()->inicio->format('Y-m-d H:i'));
    }

    public function test_lo_quiero_aparta_el_hueco_con_su_servicio_habitual(): void
    {
        Ajuste::guardar('huecos_auto', '1');
        [$luis, $pedro, $cita] = $this->escenario();
        app(CitaService::class)->cancelar($cita);

        $dif = Difusion::where('cliente_id', $luis->id)->sole();
        $this->postWebhook($luis, ['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => "dif:{$dif->id}", 'title' => 'Lo quiero']]]);

        $botones = array_column(array_column(collect($this->enviados())->last()['interactive']['action']['buttons'], 'reply'), 'id');
        $this->assertSame("conf:{$this->corte()->id}:2026-10-22T16:00", $botones[0]);

        // Pedro contesta "Sí" a la plantilla después de que Luis confirmó: se le ofrece lo que queda.
        $this->postWebhook($luis, ['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $botones[0], 'title' => 'Confirmar']]]);
        $this->assertSame($luis->id, Cita::where('inicio', '2026-10-22 16:00:00')->where('estado', Cita::CONFIRMADA)->value('cliente_id'));

        $this->postWebhook($pedro, ['type' => 'text', 'text' => ['body' => 'Sí']]);
        $aPedro = collect($this->enviados())->where('to', $pedro->wa_id)->values();
        $this->assertStringContainsString('alguien más ganó', $aPedro[$aPedro->count() - 3]['text']['body']);
        $this->assertNotContains("hora:{$this->corte()->id}:2026-10-22T16:00", array_column($aPedro->last()['interactive']['action']['sections'][0]['rows'], 'id'));
    }

    public function test_les_toca_volver_a_los_15_dias_y_no_se_repite(): void
    {
        $luis  = $this->cliente('Luis', '5213310000001');
        $pedro = $this->cliente('Pedro', '5213310000002');
        $memo  = $this->cliente('Memo', '5213310000003');
        $this->visitaPasada($luis, $this->corte(), '2026-10-04 12:00');   // 16 días
        $this->visitaPasada($pedro, $this->corte(), '2026-10-12 12:00');  // 8 días: todavía no
        $this->visitaPasada($memo, $this->corte(), '2026-10-01 12:00');   // 19 días, pero ya tiene cita
        app(CitaService::class)->agendar($memo, $this->corte(), Carbon::parse('2026-10-24 12:00'));

        Artisan::call('campanas:regresos');
        $this->assertSame([], $this->enviados(), 'Apagado no envía');

        Ajuste::guardar('regreso_auto', '1');
        Artisan::call('campanas:regresos');
        Artisan::call('campanas:regresos');

        $this->assertSame(["{$luis->wa_id}:template"], $this->aQuien());
        $this->assertSame('volver_a_agendar', $this->enviados()[0]['template']['name']);
        $this->assertSame(['Luis', '16', 'Corte de cabello'], array_column($this->enviados()[0]['template']['components'][0]['parameters'], 'text'));
        $this->assertSame(1, Recontacto::count());

        // Luis contesta "sí": le salen los días con lugar para corte.
        $this->postWebhook($luis, ['type' => 'text', 'text' => ['body' => 'sí']]);
        $this->assertStringStartsWith("dia:{$this->corte()->id}:", collect($this->enviados())->last()['interactive']['action']['sections'][0]['rows'][0]['id']);
    }

    public function test_pagina_publica_de_espacios_libres(): void
    {
        config(['whatsapp.numero_publico' => '15551518872']);

        $this->get('/libres')
            ->assertOk()
            ->assertSee('Cortes de hombre')
            ->assertSee('https://wa.me/15551518872?text=', false);
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
}
