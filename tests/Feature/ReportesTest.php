<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Reportes\Reportes;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    private function duena(): User
    {
        return User::factory()->create();
    }

    private function cita(Cliente $c, Servicio $s, string $cuando, string $estado): Cita
    {
        $inicio = Carbon::parse($cuando);

        return Cita::create([
            'cliente_id' => $c->id, 'servicio_id' => $s->id, 'seccion_id' => $s->seccion_id,
            'inicio' => $inicio, 'fin' => $inicio->copy()->addMinutes($s->duracion_minutos),
            'estado' => $estado, 'origen' => Cita::ORIGEN_PANEL,
        ]);
    }

    /** Octubre: 2 cortes atendidos, 1 tinte atendido, 1 corte cancelado, 1 manicura sin asistir. */
    private function escenario(): void
    {
        $tinte    = Servicio::where('nombre', 'Tinte o decoloración')->firstOrFail();
        $manicura = Servicio::where('nombre', 'Manicura')->firstOrFail();

        $this->cita($this->clienta('Ana'), $this->corte(), '2026-10-06 10:00', Cita::COMPLETADA);
        $this->cita($this->clienta('Rocío'), $this->corte(), '2026-10-07 12:00', Cita::COMPLETADA);
        $this->cita($this->clienta('Ana'), $tinte, '2026-10-07 10:00', Cita::COMPLETADA);
        $this->cita($this->clienta('Rocío'), $this->corte(), '2026-10-08 16:00', Cita::CANCELADA);
        $this->cita($this->clienta('Fernanda'), $manicura, '2026-10-09 11:00', Cita::NO_ASISTIO);
    }

    public function test_el_mas_pedido_es_el_corte(): void
    {
        $this->escenario();
        $r = app(Reportes::class)->generar('servicios', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31 23:59:59'));

        $this->assertSame('Corte de cabello', $r['filas'][0][0]);
        $this->assertSame(2, $r['filas'][0][2]);          // pedidas (sin la cancelada)
        $this->assertSame(1, $r['filas'][0][4]);          // canceladas o faltas
        $this->assertSame('Corte de cabello', $r['resumen']['Más pedido']);
    }

    public function test_ventas_suma_lo_atendido_y_lo_perdido(): void
    {
        $this->escenario();
        $r = app(Reportes::class)->generar('ventas', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31 23:59:59'));

        // Corte 180 x 2 + tinte 900 = 1,260 obtenido. Perdido: corte 180 + manicura 250 = 430.
        $this->assertEqualsWithDelta(1260, $r['total'][2], 0.01);
        $this->assertEqualsWithDelta(430, $r['total'][4], 0.01);
        $this->assertCount(4, $r['filas'], 'Solo los días con citas');
    }

    public function test_periodo_semana_va_de_lunes_a_domingo(): void
    {
        [$desde, $hasta] = app(Reportes::class)->periodo('semana', Carbon::parse('2026-10-08'));

        $this->assertSame('2026-10-05 00:00', $desde->format('Y-m-d H:i'));
        $this->assertSame('2026-10-11 23:59', $hasta->format('Y-m-d H:i'));
    }

    public function test_la_pantalla_muestra_cada_tipo_de_reporte(): void
    {
        $this->escenario();
        $this->actingAs($this->duena());

        foreach (array_keys(Reportes::TIPOS) as $tipo) {
            $this->get("/reportes?periodo=mes&fecha=2026-10-15&tipo={$tipo}")
                ->assertOk()
                ->assertSee(Reportes::TIPOS[$tipo]);
        }
    }

    public function test_descarga_pdf_y_excel(): void
    {
        $this->escenario();
        $this->actingAs($this->duena());

        $pdf = $this->get('/reportes/pdf?periodo=semana&fecha=2026-10-07&tipo=servicios');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->get('/reportes/excel?periodo=dia&fecha=2026-10-07&tipo=detalle');
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', file_get_contents($excel->getFile()->getPathname()));
    }

    public function test_sin_sesion_no_hay_reportes(): void
    {
        $this->get('/reportes')->assertRedirect('/login');
        $this->get('/reportes/excel')->assertRedirect('/login');
    }

    public function test_rechaza_un_tipo_que_no_existe(): void
    {
        $this->actingAs($this->duena());
        $this->get('/reportes?tipo=inventado')->assertRedirect();
    }
}
