<?php

namespace Tests\Feature\Web;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Ana López',
            'email' => 'ana@example.com',
            'telefono' => '33 1234 5678',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'acepta_avisos' => true,
        ], $cambios);
    }

    public function test_registro_crea_cuenta_de_clienta_y_devuelve_token(): void
    {
        $respuesta = $this->postJson('/api/web/auth/registro', $this->datosRegistro());

        $respuesta->assertCreated()
            ->assertJsonPath('tipo', 'Bearer')
            ->assertJsonPath('usuario.rol', 'cliente')
            ->assertJsonPath('usuario.cliente.telefono', '3312345678');
        $this->assertNotEmpty($respuesta->json('token'));

        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertDatabaseHas('clientes', ['telefono' => '3312345678', 'user_id' => $usuario->id, 'acepta_avisos' => true]);
    }

    public function test_registro_vincula_a_la_clienta_que_ya_registro_la_duena(): void
    {
        $existente = Cliente::factory()->create(['telefono' => '3312345678', 'nombre' => 'Ana (panel)']);

        $this->postJson('/api/web/auth/registro', $this->datosRegistro(['telefono' => '+52 33 1234 5678']))->assertCreated();

        $this->assertSame(1, Cliente::count());
        $existente->refresh();
        $this->assertNotNull($existente->user_id);
        $this->assertSame('Ana (panel)', $existente->nombre);
    }

    public function test_registro_valida_telefono_email_y_contrasena(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/web/auth/registro', $this->datosRegistro(['telefono' => '123']))
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'telefono']);

        $this->postJson('/api/web/auth/registro', $this->datosRegistro(['email' => 'otra@example.com', 'password_confirmation' => 'distinta']))
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_un_telefono_con_cuenta_no_se_registra_dos_veces(): void
    {
        $this->postJson('/api/web/auth/registro', $this->datosRegistro())->assertCreated();

        $this->postJson('/api/web/auth/registro', $this->datosRegistro(['email' => 'otra@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors(['telefono']);
    }

    public function test_login_correcto_y_fallido(): void
    {
        User::factory()->create(['email' => 'duena@example.com', 'password' => 'secreto123', 'rol' => 'admin']);

        $this->postJson('/api/web/auth/login', ['email' => 'duena@example.com', 'password' => 'secreto123'])
            ->assertOk()->assertJsonPath('usuario.rol', 'admin')->assertJsonStructure(['token', 'tipo', 'usuario']);

        $this->postJson('/api/web/auth/login', ['email' => 'duena@example.com', 'password' => 'incorrecta'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_una_cuenta_desactivada_no_puede_iniciar_sesion(): void
    {
        User::factory()->create(['email' => 'baja@example.com', 'password' => 'secreto123', 'activo' => false]);

        $this->postJson('/api/web/auth/login', ['email' => 'baja@example.com', 'password' => 'secreto123'])
            ->assertForbidden()->assertJsonPath('message', 'La cuenta está desactivada.');
    }

    public function test_logout_revoca_el_token(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/web/auth/me')->assertOk()->assertJsonPath('data.email', $usuario->email);
        $this->withToken($token)->postJson('/api/web/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/web/auth/me')->assertUnauthorized();
    }
}
