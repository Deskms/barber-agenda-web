<?php

namespace Tests\Feature;

use App\Events\EspacioLiberado;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Agenda\CitaService;
use App\Services\Clientes\Clientas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ClientasBajaTest extends TestCase
{
    public function test_borrar_clienta_anonimiza_y_libera_su_numero(): void
    {
        $this->fingirMeta();
        $ana = $this->clienta();
        $numero = $ana->wa_id;
        $citas = app(CitaService::class);

        // Una cita pasada (completada) y una futura.
        $pasada = $citas->agendar($ana, $this->corte(), Carbon::parse('2026-10-20 10:00'));
        $pasada->forceFill(['estado' => Cita::COMPLETADA])->save();
        $futura = $citas->agendar($ana, $this->corte(), Carbon::parse('2026-10-22 12:00'));
        $ana->createToken('app');

        Event::fake([EspacioLiberado::class]);
        $this->actingAs(User::factory()->create())
            ->delete('/clientas/' . $ana->id)
            ->assertRedirect('/clientas')
            ->assertSessionHas('ok');

        $ana->refresh();
        $this->assertNotNull($ana->eliminada_en);
        $this->assertSame(Clientas::NOMBRE_ELIMINADA, $ana->nombre);
        $this->assertNotSame($numero, $ana->wa_id);
        $this->assertFalse($ana->acepta_avisos);
        $this->assertSame(0, $ana->tokens()->count());

        // La pasada se queda para los reportes; la futura se cancela sin avisar a nadie.
        $this->assertSame(Cita::COMPLETADA, $pasada->fresh()->estado);
        $this->assertSame(Cita::CANCELADA, $futura->fresh()->estado);
        Event::assertNotDispatched(EspacioLiberado::class);

        // Ya no sale en la lista y su número se puede registrar de nuevo.
        $this->get('/clientas')->assertOk()->assertDontSee('>' . Clientas::NOMBRE_ELIMINADA . '</a>', false);
        $nueva = Cliente::firstOrCreate(['wa_id' => $numero], ['nombre' => 'Ana']);
        $this->assertNotSame($ana->id, $nueva->id);
    }

    public function test_inactiva_a_los_veinte_dias_sin_visita_y_se_reactiva_al_agendar(): void
    {
        $this->fingirMeta();
        $ana = $this->clienta();
        $rocio = $this->clienta('Rocío');
        Cliente::query()->update(['created_at' => Carbon::now()->subDays(60)]);

        // Ana vino hace 10 días; Rocío hace 25.
        foreach ([[$ana, 10], [$rocio, 25]] as [$c, $dias]) {
            Cita::create([
                'cliente_id' => $c->id, 'servicio_id' => $this->corte()->id, 'seccion_id' => $this->corte()->seccion_id,
                'inicio' => Carbon::now()->subDays($dias)->setTime(12, 0), 'fin' => Carbon::now()->subDays($dias)->setTime(13, 0),
                'estado' => Cita::COMPLETADA, 'origen' => Cita::ORIGEN_PANEL,
            ]);
        }

        $this->artisan('clientas:inactivas')->assertSuccessful();

        $this->assertNull($ana->fresh()->inactiva_desde);
        $this->assertNotNull($rocio->fresh()->inactiva_desde);

        $this->actingAs(User::factory()->create())
            ->get('/clientas?estado=inactivas')->assertOk()->assertSee('Rocío')->assertDontSee('>Ana<', false);

        // En cuanto agenda vuelve a activa.
        app(CitaService::class)->agendar($rocio->fresh(), $this->corte(), Carbon::parse('2026-10-21 12:00'));
        $this->assertNull($rocio->fresh()->inactiva_desde);
    }
}
