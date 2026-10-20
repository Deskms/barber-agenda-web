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
