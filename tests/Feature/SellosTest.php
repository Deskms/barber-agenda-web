<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Lealtad\TarjetaSellos;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Tarjeta de sellos (INN-03, SCRUM-68 a 70). */
class SellosTest extends TestCase
{
    private function sellos(): TarjetaSellos
    {
        return app(TarjetaSellos::class);
    }

    /** Cita ya pasada y confirmada, lista para que la dueña la marque. */
    private function citaPasada(Cliente $cliente, int $diasAtras = 1): Cita
    {
        $servicio = $this->corte();
        $inicio   = Carbon::parse(self::AHORA)->subDays($diasAtras)->setTime(10, 0);

        return Cita::create([
            'cliente_id'  => $cliente->id,
            'servicio_id' => $servicio->id,
            'seccion_id'  => $servicio->seccion_id,
            'inicio'      => $inicio,
            'fin'         => $inicio->copy()->addMinutes($servicio->duracion_minutos),
            'estado'      => Cita::CONFIRMADA,
            'origen'      => Cita::ORIGEN_PANEL,
        ]);
    }

    private function marcar(Cita $cita, string $estado)
    {
        return $this->post("/citas/{$cita->id}/estado", ['estado' => $estado]);
    }

    public function test_una_cita_completada_suma_un_sello_y_no_se_duplica(): void
    {
        $this->actingAs(User::factory()->create());
        $ana  = $this->clienta('Ana');
        $cita = $this->citaPasada($ana);

        $this->marcar($cita, Cita::COMPLETADA)->assertSessionHas('ok');
        $this->marcar($cita, Cita::COMPLETADA);

        $this->assertSame(1, $this->sellos()->estado($ana)['sellos']);
    }

    public function test_revertir_la_cita_quita_el_sello(): void
    {
        $this->actingAs(User::factory()->create());
        $ana  = $this->clienta('Ana');
        $cita = $this->citaPasada($ana);

        $this->marcar($cita, Cita::COMPLETADA);
        $this->marcar($cita, Cita::NO_ASISTIO);

        $this->assertSame(0, $this->sellos()->estado($ana)['sellos']);
    }

    public function test_al_llenar_la_tarjeta_avisa_una_sola_vez_y_respeta_el_consentimiento(): void
    {
        $this->fingirMeta();
        $this->actingAs(User::factory()->create());
        Ajuste::guardar('sellos_meta', '2');

        $ana = $this->clienta('Ana');
        $ana->update(['acepta_avisos' => true]);
        $primera = $this->citaPasada($ana, 2);
        $segunda = $this->citaPasada($ana, 1);

        $this->marcar($primera, Cita::COMPLETADA);
        $this->assertCount(0, $this->enviados());

        $this->marcar($segunda, Cita::COMPLETADA);
        $this->marcar($segunda, Cita::COMPLETADA);

        $enviados = $this->enviados();
        $this->assertCount(1, $enviados);
        $this->assertSame('tarjeta_completa', $enviados[0]['template']['name']);

        // Sin consentimiento no sale nada, aunque llene la tarjeta.
        $rocio = $this->clienta('Rocío');
        $rocio->update(['acepta_avisos' => false]);
        $this->marcar($this->citaPasada($rocio, 2), Cita::COMPLETADA);
        $this->marcar($this->citaPasada($rocio, 1), Cita::COMPLETADA);

        $this->assertCount(1, $this->enviados());
    }

    public function test_entregar_el_premio_descuenta_la_meta(): void
    {
        $this->fingirMeta();
        $this->actingAs(User::factory()->create());
        Ajuste::guardar('sellos_meta', '2');
        $ana = $this->clienta('Ana');

        $this->post("/clientas/{$ana->id}/canjear")->assertSessionHasErrors('sellos');

        $this->marcar($this->citaPasada($ana, 2), Cita::COMPLETADA);
        $this->marcar($this->citaPasada($ana, 1), Cita::COMPLETADA);
        $this->assertSame(1, $this->sellos()->estado($ana)['premios']);

        $this->post("/clientas/{$ana->id}/canjear")->assertSessionHas('ok');
        $this->post("/clientas/{$ana->id}/canjear")->assertSessionHasErrors('sellos');

        $estado = $this->sellos()->estado($ana);
        $this->assertSame(0, $estado['premios']);
        $this->assertSame(0, $estado['sellos']);
    }

    public function test_la_tarjeta_publica_solo_abre_con_su_identificador(): void
    {
        $ana   = $this->clienta('Ana');
        $url   = $this->sellos()->url($ana);
        $token = $ana->fresh()->tarjeta_token;

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $token);
        $this->assertStringEndsWith('/tarjeta/' . $token, $url);
        $this->assertStringNotContainsString($ana->wa_id, $token);

        $this->get("/tarjeta/{$token}")->assertOk()->assertSee('de 10')->assertDontSee($ana->wa_id);
        $this->get('/tarjeta/' . str_repeat('a', 40))->assertNotFound();
        $this->get("/tarjeta/{$ana->id}")->assertNotFound();
    }

    public function test_la_app_consulta_sus_sellos(): void
    {
        $ana = $this->clienta('Ana');
        $this->citaPasada($ana)->update(['estado' => Cita::COMPLETADA]);

        $token = $ana->createToken('pruebas', ['movil'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/movil/sellos')
            ->assertOk()
            ->assertJsonPath('data.sellos', 1)
            ->assertJsonPath('data.meta', 10)
            ->assertJsonPath('data.faltan', 9)
            ->assertJsonPath('data.premios_disponibles', 0);

    }
}
