<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();
    }

    private function inicios($respuesta): array
    {
        return collect($respuesta->json('slots'))->map(fn ($s) => substr($s['inicio'], 11, 5))->all();
    }

    public function test_ofrece_todos_los_inicios_de_un_dia_libre(): void
    {
        $corte = $this->servicioSimple();

        $r = $this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10")->assertOk();
        $inicios = $this->inicios($r);

        $this->assertSame('09:00', $inicios[0]);
        $this->assertSame('18:00', end($inicios));
        $this->assertCount(37, $inicios);
        $this->assertSame('2026-11-10T09:00:00-06:00', $r->json('slots.0.inicio'));
    }

    public function test_un_dia_sin_atencion_no_tiene_horarios(): void
    {
        $corte = $this->servicioSimple();

        $this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-09")
            ->assertOk()->assertJsonPath('horario', null)->assertJsonCount(0, 'slots');
    }

    public function test_un_corte_agendado_quita_los_horarios_que_chocan(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, '2026-11-10 10:00')->assertCreated();

        $inicios = $this->inicios($this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10"));

        $this->assertContains('09:00', $inicios);
        $this->assertNotContains('09:15', $inicios);
        $this->assertNotContains('10:00', $inicios);
        $this->assertNotContains('10:45', $inicios);
        $this->assertContains('11:00', $inicios);
    }

    public function test_durante_el_procesamiento_de_un_tinte_se_ofrecen_cortes(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $this->agendarComoAdmin($admin, $this->tinte(), $this->clienta()->cliente, '2026-11-10 09:00')->assertCreated();

        $inicios = $this->inicios($this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10"));

        $this->assertNotContains('09:00', $inicios); // choca con la aplicación 09:00-09:30
        $this->assertNotContains('09:15', $inicios);
        $this->assertContains('09:30', $inicios);    // cae en el procesamiento
        $this->assertContains('10:30', $inicios);
        $this->assertNotContains('11:00', $inicios); // 11:00-12:00 choca con el enjuague 11:30-12:00
        $this->assertContains('12:00', $inicios);
    }

    public function test_un_dia_lleno_no_ofrece_horarios(): void
    {
        $admin = $this->admin();
        $corte = $this->servicioSimple();

        foreach (range(9, 18) as $hora) {
            $this->agendarComoAdmin($admin, $corte, $this->clienta()->cliente, sprintf('2026-11-10 %02d:00', $hora))->assertCreated();
        }

        $this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10")->assertOk()->assertJsonCount(0, 'slots');
    }

    public function test_no_ofrece_horarios_que_ya_pasaron(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:10:00'));
        $corte = $this->servicioSimple();

        $inicios = $this->inicios($this->getJson("/api/web/disponibilidad?servicio_id={$corte->id}&fecha=2026-11-10"));

        $this->assertSame('12:15', $inicios[0]);
    }

    public function test_valida_los_parametros(): void
    {
        $corte = $this->servicioSimple();
        $corte->update(['activo' => false]);

        $this->getJson('/api/web/disponibilidad?servicio_id='.$corte->id.'&fecha=10/11/2026')
            ->assertUnprocessable()->assertJsonValidationErrors(['servicio_id', 'fecha']);
    }
}
