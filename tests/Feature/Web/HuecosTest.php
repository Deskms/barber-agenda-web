<?php

namespace Tests\Feature\Web;

use App\Models\Cliente;
use App\Models\Hueco;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_un_hueco_deja_de_estar_libre_cuando_alguien_lo_toma(): void
    {
        $this->agendarComoAdmin($this->admin(), $this->servicioSimple('Otro corte'), Cliente::factory()->create(), '2026-11-10 10:00')->assertCreated();

        $this->getJson('/api/web/admin/huecos')->assertOk()->assertJsonPath('data.0.sigue_libre', false);
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
}
