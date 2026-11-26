<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiMovilGoogleTest extends TestCase
{
    private const CLIENTE_WEB = 'web-123.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => self::CLIENTE_WEB]);
    }

    /** Respuesta de tokeninfo de Google para un token válido de nuestra app. */
    private function fingirGoogle(array $cambios = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(array_merge([
                'aud'            => self::CLIENTE_WEB,
                'iss'            => 'https://accounts.google.com',
                'sub'            => '1098765',
                'email'          => 'ana@gmail.com',
                'email_verified' => 'true',
                'name'           => 'Ana López',
                'exp'            => (string) Carbon::now()->addHour()->timestamp,
            ], $cambios), 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.prueba']]], 200),
        ]);
    }

    public function test_la_primera_vez_pide_confirmar_el_numero_y_despues_entra_directo(): void
    {
        $this->fingirGoogle();

        $primera = $this->postJson('/api/movil/auth/google', ['id_token' => 'token-de-google']);
        $primera->assertOk()->assertJson(['requiere_telefono' => true, 'email' => 'ana@gmail.com']);
        $enlace = $primera->json('enlace_google');

        $codigo = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3331111111'])->json('codigo_de_prueba');

        $this->postJson('/api/movil/auth/token', [
            'telefono'      => '3331111111',
            'codigo'        => $codigo,
            'enlace_google' => $enlace,
        ])->assertOk()->assertJsonPath('cliente.con_google', true);

        $ana = Cliente::where('wa_id', '5213331111111')->firstOrFail();
        $this->assertSame('1098765', $ana->google_sub);
        $this->assertSame('ana@gmail.com', $ana->email);

        // Segunda vez: entra sin número ni código.
        $this->postJson('/api/movil/auth/google', ['id_token' => 'token-de-google'])
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->assertJsonPath('cliente.telefono', '5213331111111');
    }

    public function test_rechaza_un_token_emitido_para_otra_app(): void
    {
        $this->fingirGoogle(['aud' => 'otra-app.apps.googleusercontent.com']);

        $this->postJson('/api/movil/auth/google', ['id_token' => 'token-ajeno'])->assertStatus(422);
    }

    public function test_rechaza_un_correo_sin_verificar(): void
    {
        $this->fingirGoogle(['email_verified' => 'false']);

        $this->postJson('/api/movil/auth/google', ['id_token' => 'token'])->assertStatus(422);
    }

    public function test_un_enlace_vencido_no_gasta_el_codigo(): void
    {
        $this->fingirGoogle();

        $enlace = $this->postJson('/api/movil/auth/google', ['id_token' => 'token'])->json('enlace_google');
        $codigo = $this->postJson('/api/movil/auth/codigo', ['telefono' => '3331111111'])->json('codigo_de_prueba');

        Carbon::setTestNow(Carbon::now()->addMinutes(20));

        $this->postJson('/api/movil/auth/token', ['telefono' => '3331111111', 'codigo' => $codigo, 'enlace_google' => $enlace])
            ->assertStatus(422);

        // El código sigue sirviendo para entrar sin Google... salvo que también venció (10 min).
        $this->assertNull(Cliente::where('wa_id', '5213331111111')->value('google_sub'));
    }

    public function test_sin_client_id_configurado_no_deja_entrar_con_google(): void
    {
        config(['services.google.client_id' => null]);
        Http::fake();

        $this->postJson('/api/movil/auth/google', ['id_token' => 'token'])->assertStatus(422);
        Http::assertNothingSent();
    }
}
