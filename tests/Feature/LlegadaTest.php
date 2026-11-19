<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Services\Agenda\Llegadas;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** La clienta registra su llegada escaneando el QR del mostrador con la app. */
class LlegadaTest extends TestCase
{
    private function cita(Cliente $cliente, string $hora): Cita
    {
        $servicio = $this->corte();
        $inicio   = Carbon::parse('2026-10-20 ' . $hora);

        return Cita::create([
            'cliente_id'  => $cliente->id,
            'servicio_id' => $servicio->id,
            'seccion_id'  => $servicio->seccion_id,
            'inicio'      => $inicio,
            'fin'         => $inicio->copy()->addMinutes($servicio->duracion_minutos),
            'estado'      => Cita::CONFIRMADA,
            'origen'      => Cita::ORIGEN_MOVIL,
        ]);
    }

    private function comoClienta(Cliente $cliente): self
    {
        $token = $cliente->createToken('pruebas', ['movil'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_escanear_el_qr_registra_la_llegada_una_sola_vez(): void
    {
        $ana  = $this->clienta('Ana');
        $cita = $this->cita($ana, '09:30');
        $qr   = app(Llegadas::class)->url();

        $this->comoClienta($ana)->postJson('/api/movil/llegada', ['codigo' => $qr])
            ->assertOk()
            ->assertJsonPath('data.id', $cita->id)
            ->assertJsonPath('data.llegada_en', '2026-10-20T09:00:00-06:00');

        Carbon::setTestNow(Carbon::parse(self::AHORA)->addMinutes(10));
        $this->comoClienta($ana)->postJson('/api/movil/llegada', ['codigo' => $qr])->assertOk();

        $this->assertSame('09:00', $cita->fresh()->llegada_en->format('H:i'));
    }

    public function test_un_codigo_ajeno_no_sirve(): void
    {
        $ana = $this->clienta('Ana');
        $this->cita($ana, '09:30');

        $this->comoClienta($ana)->postJson('/api/movil/llegada', ['codigo' => 'https://otro.sitio/llegada/' . str_repeat('x', 32)])
            ->assertStatus(422);
    }

    public function test_sin_cita_cercana_no_registra_nada(): void
    {
        $ana = $this->clienta('Ana');
        $lejana = $this->cita($ana, '17:00');

        $this->comoClienta($ana)->postJson('/api/movil/llegada', ['codigo' => app(Llegadas::class)->codigo()])
            ->assertNotFound()
            ->assertJsonPath('mensaje', 'Tu próxima cita es hoy a las 17:00. Puedes registrar tu llegada desde 60 minutos antes.');

        $this->assertNull($lejana->fresh()->llegada_en);
    }

    public function test_cambiar_el_codigo_invalida_el_qr_impreso(): void
    {
        $ana = $this->clienta('Ana');
        $this->cita($ana, '09:30');
        $llegadas = app(Llegadas::class);
        $viejo = $llegadas->url();
        $llegadas->cambiarCodigo();

        $this->comoClienta($ana)->postJson('/api/movil/llegada', ['codigo' => $viejo])->assertStatus(422);
        $this->get($viejo)->assertOk();
    }

    public function test_la_app_recibe_la_ubicacion_del_local(): void
    {
        config(['whatsapp.negocio.lat' => 20.6767, 'whatsapp.negocio.lng' => -103.3475]);

        $this->comoClienta($this->clienta('Ana'))->getJson('/api/movil/negocio')
            ->assertOk()
            ->assertJsonPath('data.lat', 20.6767)
            ->assertJsonPath('data.lng', -103.3475);
    }
}
