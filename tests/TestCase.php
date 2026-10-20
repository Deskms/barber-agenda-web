<?php

namespace Tests;

use App\Models\Cliente;
use App\Models\Servicio;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Martes 20 de octubre de 2026, 09:00. El negocio abre martes a sábado de 10 a 19. */
    protected const AHORA = '2026-10-20 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::AHORA));
        $this->seed(CatalogoSeeder::class);

        // Ninguna prueba debe salir a internet.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function corte(): Servicio
    {
        return Servicio::where('nombre', 'Corte de cabello')->firstOrFail();
    }

    protected function clienta(string $nombre = 'Ana'): Cliente
    {
        return Cliente::where('nombre', $nombre)->firstOrFail();
    }

    /** Respuesta exitosa de la Cloud API para cualquier envío. */
    protected function fingirMeta(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.prueba']]], 200),
        ]);
    }

    /** Cuerpos de los mensajes que se enviaron a Meta, en orden. */
    protected function enviados(): array
    {
        return collect(Http::recorded())
            ->map(fn ($par) => $par[0]->data())
            ->filter(fn ($d) => isset($d['messaging_product']))
            ->values()
            ->all();
    }
}
