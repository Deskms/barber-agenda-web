<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    public function test_las_rutas_protegidas_piden_token(): void
    {
        $this->getJson('/api/web/citas')->assertUnauthorized()->assertJsonPath('message', 'No autenticado.');
        $this->getJson('/api/web/admin/agenda')->assertUnauthorized();
        $this->postJson('/api/web/admin/servicios', [])->assertUnauthorized();
    }

    public function test_una_clienta_no_entra_a_rutas_de_administracion(): void
    {
        $this->como($this->clienta());

        $this->postJson('/api/web/admin/servicios', ['nombre' => 'X', 'duracion_minutos' => 30])->assertForbidden();
        $this->getJson('/api/web/admin/agenda')->assertForbidden();
        $this->getJson('/api/web/admin/clientes')->assertForbidden();
        $this->getJson('/api/web/admin/usuarios')->assertForbidden();
        $this->postJson('/api/web/admin/bloqueos', [])->assertForbidden();
    }

    public function test_la_duena_si_entra_y_no_usa_rutas_de_clienta(): void
    {
        $this->como($this->admin());

        $this->getJson('/api/web/admin/agenda')->assertOk();
        $this->getJson('/api/web/mi/historial')->assertForbidden();
    }

    public function test_un_token_de_cuenta_desactivada_deja_de_servir(): void
    {
        $this->como(User::factory()->create(['activo' => false]));

        $this->getJson('/api/web/citas')->assertForbidden();
    }

    public function test_la_duena_administra_usuarios_y_desactivar_revoca_tokens(): void
    {
        $admin = $this->admin();
        $clienta = $this->clienta();
        $clienta->createToken('web');

        $this->como($admin)->postJson('/api/web/admin/usuarios', [
            'nombre' => 'Segunda admin', 'email' => 'admin2@example.com', 'password' => 'secreto123', 'rol' => 'admin',
        ])->assertCreated()->assertJsonPath('data.rol', 'admin');

        $this->patchJson("/api/web/admin/usuarios/{$clienta->id}", ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);
        $this->assertSame(0, $clienta->tokens()->count());

        $this->patchJson("/api/web/admin/usuarios/{$admin->id}", ['activo' => false])->assertUnprocessable();
    }
}
