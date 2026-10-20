<?php

namespace Tests\Feature;

use App\Events\EspacioLiberado;
use App\Exceptions\HorarioNoDisponibleException;
use App\Models\Cita;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    private function disponibilidad(): DisponibilidadService
    {
        return app(DisponibilidadService::class);
    }

    private function citas(): CitaService
    {
        return app(CitaService::class);
    }

    public function test_citas_contiguas_no_chocan(): void
    {
        $this->citas()->agendar($this->clienta('Ana'), $this->corte(), Carbon::parse('2026-10-20 10:00'));

        $segunda = $this->citas()->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse('2026-10-20 11:00'));

        $this->assertSame('11:00', $segunda->inicio->format('H:i'));
    }

    public function test_no_permite_traslape_ni_doble_reserva(): void
    {
        $this->citas()->agendar($this->clienta('Ana'), $this->corte(), Carbon::parse('2026-10-20 10:00'));

        $this->expectException(HorarioNoDisponibleException::class);
        $this->citas()->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse('2026-10-20 10:30'));
    }

    public function test_rechaza_fuera_de_horario_dia_cerrado_y_pasado(): void
    {
        foreach (['2026-10-20 18:30', '2026-10-25 12:00', '2026-10-19 12:00', '2026-10-20 08:00'] as $momento) {
            try {
                $this->citas()->agendar($this->clienta(), $this->corte(), Carbon::parse($momento));
                $this->fail("Se aceptó {$momento}");
            } catch (HorarioNoDisponibleException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Cita::count());
    }

    public function test_slots_respetan_bordes_y_dia_lleno(): void
    {
        $slots = $this->disponibilidad()->slotsDisponibles($this->corte(), Carbon::parse('2026-10-20'));

        $this->assertSame('10:00', $slots->first()['inicio']->format('H:i'));
        $this->assertSame('18:00', $slots->last()['inicio']->format('H:i'), 'El último corte debe terminar justo al cierre');

        // Llena el día completo con citas de una hora.
        foreach (range(10, 18) as $hora) {
            $this->citas()->agendar($this->clienta(), $this->corte(), Carbon::parse("2026-10-20 {$hora}:00"));
        }

        $this->assertTrue($this->disponibilidad()->slotsDisponibles($this->corte(), Carbon::parse('2026-10-20'))->isEmpty());
        $this->assertTrue($this->disponibilidad()->slotsDisponibles($this->corte(), Carbon::parse('2026-10-25'))->isEmpty(), 'Domingo cerrado');
    }

    private function tinte(): \App\Models\Servicio
    {
        return \App\Models\Servicio::where('nombre', 'Tinte o decoloración')->firstOrFail();
    }

    private function tramos(?int $seccionId): array
    {
        return $this->disponibilidad()->tramosDelDia(Carbon::parse('2026-10-20'), $seccionId)
            ->map(fn ($t) => $t['inicio']->format('H:i') . '-' . $t['fin']->format('H:i') . ($t['libre'] ? ' libre' : ' ocupado'))
            ->all();
    }

    private function inicios($servicio): array
    {
        return $this->disponibilidad()->slotsDisponibles($servicio, Carbon::parse('2026-10-20'))
            ->map(fn ($s) => $s['inicio']->format('H:i'))->all();
    }

    public function test_un_tinte_no_ocupa_la_seccion_de_cortes(): void
    {
        // Tinte de 15 a 18 en color: en cortes siguen cabiendo tres cortes de 15 a 18.
        $this->citas()->agendar($this->clienta('Ana'), $this->tinte(), Carbon::parse('2026-10-20 15:00'));

        foreach (['15:00', '16:00', '17:00'] as $hora) {
            $this->citas()->agendar($this->clienta('Rocío'), $this->corte(), Carbon::parse("2026-10-20 {$hora}"));
        }

        $this->assertSame(['10:00-15:00 libre', '15:00-18:00 ocupado', '18:00-19:00 libre'], $this->tramos($this->tinte()->seccion_id));
        $this->assertSame(['10:00-15:00 libre', '15:00-18:00 ocupado', '18:00-19:00 libre'], $this->tramos($this->corte()->seccion_id));
        $this->assertSame(4, Cita::count());
    }

    public function test_dentro_de_una_seccion_el_tinte_cabe_solo_donde_hay_3_horas(): void
    {
        // En color: tinte de 11 a 14 y manicura de 15 a 15:45.
        $manicura = \App\Models\Servicio::where('nombre', 'Manicura')->firstOrFail();
        $this->citas()->agendar($this->clienta('Ana'), $this->tinte(), Carbon::parse('2026-10-20 11:00'));
        $this->citas()->agendar($this->clienta('Rocío'), $manicura, Carbon::parse('2026-10-20 15:00'));

        $this->assertSame(
            ['10:00-11:00 libre', '11:00-14:00 ocupado', '14:00-15:00 libre', '15:00-15:45 ocupado', '15:45-19:00 libre'],
            $this->tramos($this->tinte()->seccion_id),
        );

        $this->assertSame('15:45', $this->inicios($this->tinte())[0]);
        $this->assertContains('11:00', $this->inicios($this->corte()), 'La sección de cortes sigue libre a esa hora');
    }

    public function test_la_misma_clienta_no_puede_estar_en_dos_secciones_a_la_vez(): void
    {
        $this->citas()->agendar($this->clienta('Ana'), $this->tinte(), Carbon::parse('2026-10-20 15:00'));

        $this->expectException(HorarioNoDisponibleException::class);
        $this->citas()->agendar($this->clienta('Ana'), $this->corte(), Carbon::parse('2026-10-20 16:00'));
    }

    public function test_cancelar_es_idempotente_y_libera_el_horario(): void
    {
        Event::fake([EspacioLiberado::class]);

        $cita = $this->citas()->agendar($this->clienta(), $this->corte(), Carbon::parse('2026-10-20 12:00'));

        $this->citas()->cancelar($cita);
        $this->citas()->cancelar($cita->fresh());

        Event::assertDispatchedTimes(EspacioLiberado::class, 1);
        $this->assertTrue($this->disponibilidad()->estaLibre(Carbon::parse('2026-10-20 12:00'), Carbon::parse('2026-10-20 13:00')));
    }

    public function test_reprogramar_libera_el_anterior_y_reinicia_recordatorio(): void
    {
        $cita = $this->citas()->agendar($this->clienta(), $this->corte(), Carbon::parse('2026-10-20 12:00'));
        $cita->update(['recordatorio_enviado_en' => now()]);

        $this->citas()->reprogramar($cita->load('servicio'), Carbon::parse('2026-10-21 15:00'));

        $this->assertTrue($this->disponibilidad()->estaLibre(Carbon::parse('2026-10-20 12:00'), Carbon::parse('2026-10-20 13:00')));
        $this->assertNull($cita->fresh()->recordatorio_enviado_en);
        $this->assertSame('2026-10-21 15:00', $cita->fresh()->inicio->format('Y-m-d H:i'));
    }

    public function test_candidatas_excluyen_sin_consentimiento_quien_cancelo_y_ocupadas(): void
    {
        $ana   = $this->clienta('Ana');
        $rocio = $this->clienta('Rocío');

        $cita = $this->citas()->agendar($ana, $this->corte(), Carbon::parse('2026-10-20 12:00'));
        // Rocío ya tiene cita a esa hora en el día siguiente: no choca, sigue siendo candidata.
        $this->citas()->agendar($rocio, $this->corte(), Carbon::parse('2026-10-21 12:00'));
        $this->citas()->cancelar($cita);

        $candidatas = app(\App\Listeners\BuscarClientasParaEspacioLiberado::class)
            ->candidatas(new EspacioLiberado($cita->fresh()))
            ->pluck('nombre');

        $this->assertContains('Rocío', $candidatas);
        $this->assertNotContains('Ana', $candidatas);
        $this->assertNotContains('Fernanda', $candidatas);
    }
}
