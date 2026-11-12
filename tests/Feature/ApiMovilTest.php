<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\CodigoAcceso;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiMovilTest extends TestCase
{
    /** Entra a la app con el código que llega por WhatsApp. */
    public function test_el_codigo_se_canjea_por_un_token(): void
    {
        $this->fingirMeta();

        $envio = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3331111111']);

        $envio->assertStatus(202);
        $codigo = $envio->json('codigo_de_prueba');
        $this->assertNotNull($codigo);

        $token = $this->postJson('/api/movil/auth/token', [
            'telefono' => '3331111111',
            'codigo'   => $codigo,
        ]);

        $token->assertOk()
            ->assertJsonStructure(['token', 'cliente' => ['id', 'telefono']]);

        // El número de 10 dígitos se normaliza al formato de Meta.
        $this->assertSame('5213331111111', $token->json('cliente.telefono'));
    }

    public function test_el_codigo_no_sirve_dos_veces(): void
    {
        $this->fingirMeta();

        $codigo = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3331111111'])
            ->json('codigo_de_prueba');

        $this->postJson('/api/movil/auth/token', ['telefono' => '3331111111', 'codigo' => $codigo])
            ->assertOk();

        $this->postJson('/api/movil/auth/token', ['telefono' => '3331111111', 'codigo' => $codigo])
            ->assertStatus(422);
    }

    public function test_el_codigo_vencido_se_rechaza(): void
    {
        $this->fingirMeta();

        $codigo = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3331111111'])
            ->json('codigo_de_prueba');

        Carbon::setTestNow(Carbon::parse(self::AHORA)->addMinutes(11));

        $this->postJson('/api/movil/auth/token', ['telefono' => '3331111111', 'codigo' => $codigo])
            ->assertStatus(422);
    }

    public function test_sin_token_no_se_entra(): void
    {
        $this->getJson('/api/movil/citas')->assertStatus(401);
    }

    public function test_agenda_una_cita_desde_la_app(): void
    {
        $respuesta = $this->comoClienta($this->clienta('Ana'))
            ->postJson('/api/movil/citas', [
                'servicio_id' => $this->corte()->id,
                'inicio'      => '2026-10-20T16:00',
            ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.estado', Cita::CONFIRMADA)
            ->assertJsonPath('data.origen', Cita::ORIGEN_MOVIL)
            ->assertJsonPath('data.servicio.nombre', 'Corte de cabello');
    }

    /** El horario ocupado devuelve 409, no 500 ni una segunda cita. */
    public function test_no_agenda_sobre_un_horario_ocupado(): void
    {
        $ana = $this->clienta('Ana');

        $this->comoClienta($ana)->postJson('/api/movil/citas', [
            'servicio_id' => $this->corte()->id,
            'inicio'      => '2026-10-20T16:00',
        ])->assertStatus(201);

        $this->comoClienta($this->clienta('Rocío'))->postJson('/api/movil/citas', [
            'servicio_id' => $this->corte()->id,
            'inicio'      => '2026-10-20T16:30',
        ])->assertStatus(409);

        $this->assertSame(1, Cita::query()->vigentes()->count());
    }

    /** Lo más importante: nadie ve ni cancela citas de otra clienta. */
    public function test_solo_se_ven_las_citas_propias(): void
    {
        $this->fingirMeta();

        $ana   = $this->clienta('Ana');
        $rocio = $this->clienta('Rocío');

        $cita = $this->comoClienta($ana)->postJson('/api/movil/citas', [
            'servicio_id' => $this->corte()->id,
            'inicio'      => '2026-10-20T16:00',
        ])->json('data.id');

        $this->comoClienta($rocio)->getJson('/api/movil/citas')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Una cita ajena responde 404: no se confirma que ese id exista.
        $this->comoClienta($rocio)->postJson("/api/movil/citas/{$cita}/cancelar")
            ->assertStatus(404);

        $this->assertSame(Cita::CONFIRMADA, Cita::find($cita)->estado);
    }

    public function test_la_clienta_cancela_su_propia_cita(): void
    {
        $this->fingirMeta();

        $ana = $this->clienta('Ana');

        $cita = $this->comoClienta($ana)->postJson('/api/movil/citas', [
            'servicio_id' => $this->corte()->id,
            'inicio'      => '2026-10-20T16:00',
        ])->json('data.id');

        $this->comoClienta($ana)->postJson("/api/movil/citas/{$cita}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', Cita::CANCELADA);
    }

    public function test_el_catalogo_trae_secciones_con_servicios(): void
    {
        $this->comoClienta($this->clienta('Ana'))
            ->getJson('/api/movil/catalogo')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nombre', 'servicios' => [['id', 'nombre', 'duracion_minutos']]]]]);
    }

    public function test_la_disponibilidad_respeta_el_horario_de_atencion(): void
    {
        // Domingo: el negocio abre de martes a sábado.
        $this->comoClienta($this->clienta('Ana'))
            ->getJson('/api/movil/disponibilidad?servicio_id=' . $this->corte()->id . '&fecha=2026-10-25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_actualiza_el_consentimiento_de_avisos(): void
    {
        $fernanda = $this->clienta('Fernanda');
        $this->assertFalse($fernanda->acepta_avisos);

        $this->comoClienta($fernanda)
            ->putJson('/api/movil/perfil', ['acepta_avisos' => true])
            ->assertOk()
            ->assertJsonPath('data.acepta_avisos', true);
    }

    /** Autentica con un token real de Sanctum, no con un atajo de pruebas. */
    private function comoClienta(Cliente $cliente): self
    {
        $token = $cliente->createToken('pruebas', ['movil'])->plainTextToken;

        // En pruebas la aplicación vive entre peticiones y el guard recuerda a
        // la última usuaria; en producción cada petición arranca limpia.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}
