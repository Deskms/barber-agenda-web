<?php

namespace Tests\Feature\Web;

use App\Models\Cliente;
use App\Models\Hueco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaAgenda;
use Tests\TestCase;

class HuecosTest extends TestCase
{
    use CreaAgenda, RefreshDatabase;

    private int $huecoId;

    private Cliente $elegible;

    private Cliente $sinConsentimiento;

    private Cliente $conCita;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAgenda();

        $admin = $this->admin();
        $corte = $this->servicioSimple();
        $quienCancela = $this->clienta(['acepta_avisos' => true]);

        $this->elegible = Cliente::factory()->conAvisos()->create(['nombre' => 'Ana']);
        $this->sinConsentimiento = Cliente::factory()->create(['nombre' => 'Fernanda']);
        $this->conCita = Cliente::factory()->conAvisos()->create(['nombre' => 'Rocío']);

        $id = $this->agendarComoAdmin($admin, $corte, $quienCancela->cliente, '2026-11-10 10:00')->json('data.id');
        // Rocío ya tiene una cita que se traslapa con el hueco (en el procesamiento de un tinte).
        $this->agendarComoAdmin($admin, $this->tinte(), $this->conCita, '2026-11-10 09:00')->assertCreated();
        $this->postJson("/api/web/citas/{$id}/cancelar")->assertOk();

        $this->huecoId = Hueco::firstOrFail()->id;
        config(['whatsapp.access_token' => 'token-de-prueba', 'whatsapp.phone_id' => '123456']);
    }

    public function test_lista_huecos_y_candidatas_elegibles(): void
    {
        $this->getJson('/api/web/admin/huecos')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.inicio', '2026-11-10T10:00:00-06:00');

        $ids = collect($this->getJson("/api/web/admin/huecos/{$this->huecoId}/candidatas")->assertOk()->json('candidatas'))->pluck('id');

        $this->assertTrue($ids->contains($this->elegible->id));
        $this->assertFalse($ids->contains($this->sinConsentimiento->id));
        $this->assertFalse($ids->contains($this->conCita->id));
        $this->assertCount(1, $ids);
    }

    public function test_difundir_envia_registra_y_no_repite(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ENVIO1']]], 200)]);

        $this->postJson("/api/web/admin/huecos/{$this->huecoId}/difundir", ['cliente_ids' => [$this->elegible->id, $this->sinConsentimiento->id]])
            ->assertOk()->assertJsonPath('enviadas', [$this->elegible->id])->assertJsonCount(1, 'omitidas');

        $this->assertDatabaseHas('difusiones', ['espacio_liberado_id' => $this->huecoId, 'cliente_id' => $this->elegible->id, 'estado_envio' => 'enviado', 'wamid' => 'wamid.ENVIO1']);
        $this->assertDatabaseHas('eventos_whatsapp', ['wamid' => 'wamid.ENVIO1', 'direccion' => 'saliente', 'cliente_id' => $this->elegible->id]);
        Http::assertSentCount(1);

        $this->postJson("/api/web/admin/huecos/{$this->huecoId}/difundir", ['cliente_ids' => [$this->elegible->id]])
            ->assertOk()->assertJsonPath('enviadas', [])->assertJsonCount(1, 'omitidas');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('difusiones', 1);
    }

    public function test_un_envio_rechazado_por_meta_queda_como_fallido(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not approved', 'code' => 132001]], 400)]);

        $this->postJson("/api/web/admin/huecos/{$this->huecoId}/difundir", ['cliente_ids' => [$this->elegible->id]])
            ->assertOk()->assertJsonPath('fallidas.0.error', 'Template not approved');
        $this->assertDatabaseHas('difusiones', ['cliente_id' => $this->elegible->id, 'estado_envio' => 'fallido']);
    }

    public function test_no_se_difunde_sin_whatsapp_configurado_ni_un_hueco_ya_ocupado(): void
    {
        config(['whatsapp.access_token' => null]);
        $this->postJson("/api/web/admin/huecos/{$this->huecoId}/difundir", ['cliente_ids' => [$this->elegible->id]])->assertStatus(503);

        config(['whatsapp.access_token' => 'token-de-prueba']);
        $this->agendarComoAdmin($this->admin(), $this->servicioSimple('Otro corte'), Cliente::factory()->create(), '2026-11-10 10:00')->assertCreated();
        $this->postJson("/api/web/admin/huecos/{$this->huecoId}/difundir", ['cliente_ids' => [$this->elegible->id]])->assertStatus(409);
        $this->assertDatabaseCount('difusiones', 0);
    }
}
