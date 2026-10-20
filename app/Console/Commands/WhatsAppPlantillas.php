<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\PlantillaService;
use Illuminate\Console\Command;

/**
 * Crea y consulta las plantillas de la WABA desde la terminal.
 *
 *   php artisan whatsapp:plantillas                        → qué hay en Meta
 *   php artisan whatsapp:plantillas --crear                → manda a revisión las que falten
 *   php artisan whatsapp:plantillas --crear --solo=confirmacion_cita
 *   php artisan whatsapp:plantillas --borrar=espacio_liberado
 */
class WhatsAppPlantillas extends Command
{
    protected $signature = 'whatsapp:plantillas
                            {--crear : Envía a revisión las plantillas del catálogo que aún no existen}
                            {--solo= : Trabaja solo con esta plantilla del catálogo}
                            {--borrar= : Elimina una plantilla de la WABA por nombre}
                            {--forzar : Reenvía la plantilla aunque ya exista en Meta}';

    protected $description = 'Administra las plantillas de mensaje de WhatsApp (crear, listar, borrar)';

    public function handle(PlantillaService $plantillas): int
    {
        if (! config('whatsapp.waba_id')) {
            $this->error('Falta WHATSAPP_BUSINESS_ACCOUNT_ID en el .env');

            return self::FAILURE;
        }

        if ($nombre = $this->option('borrar')) {
            return $this->borrar($plantillas, $nombre);
        }

        $existentes = $this->existentes($plantillas);

        if ($existentes === null) {
            return self::FAILURE;
        }

        return $this->option('crear')
            ? $this->crear($plantillas, $existentes)
            : $this->listar($existentes);
    }

    /** @return array<string, array<string, mixed>>|null */
    private function existentes(PlantillaService $plantillas): ?array
    {
        $respuesta = $plantillas->listar();

        if (! $respuesta->successful()) {
            $this->fallo($respuesta->status(), $respuesta->json());

            return null;
        }

        $mapa = [];

        foreach (data_get($respuesta->json(), 'data', []) as $plantilla) {
            $mapa[$plantilla['name']] = $plantilla;
        }

        return $mapa;
    }

    private function listar(array $existentes): int
    {
        $this->line('');

        if ($existentes === []) {
            $this->warn('  La WABA no tiene ninguna plantilla todavía.');
            $this->line('  Crea las del catálogo con: php artisan whatsapp:plantillas --crear');
            $this->line('');

            return self::SUCCESS;
        }

        $this->table(
            ['Plantilla', 'Idioma', 'Categoría', 'Estado'],
            array_map(fn ($p) => [
                $p['name'],
                $p['language'] ?? '—',
                $p['category'] ?? '—',
                $this->estado($p['status'] ?? '—'),
            ], array_values($existentes)),
        );

        $faltantes = array_diff(array_keys(config('whatsapp.plantillas')), array_keys($existentes));

        if ($faltantes !== []) {
            $this->comment('  Faltan del catálogo: ' . implode(', ', $faltantes));
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function crear(PlantillaService $plantillas, array $existentes): int
    {
        $catalogo = config('whatsapp.plantillas');

        if ($solo = $this->option('solo')) {
            if (! isset($catalogo[$solo])) {
                $this->error("La plantilla «{$solo}» no está en config/whatsapp.php");

                return self::FAILURE;
            }

            $catalogo = [$solo => $catalogo[$solo]];
        }

        $this->line('');
        $errores = 0;

        foreach ($catalogo as $clave => $definicion) {
            if (isset($existentes[$clave]) && ! $this->option('forzar')) {
                $this->line("  — {$clave}: ya existe en Meta ({$existentes[$clave]['status']}), la salto.");

                continue;
            }

            $respuesta = $plantillas->crear($definicion);

            if ($respuesta->successful()) {
                $estado = data_get($respuesta->json(), 'status', 'PENDING');
                $this->info("  ✓ {$clave}: enviada a revisión ({$estado})");

                continue;
            }

            $errores++;
            $this->error("  ✗ {$clave}: " . data_get($respuesta->json(), 'error.message', 'sin detalle'));
            $this->pista((int) data_get($respuesta->json(), 'error.code'));
        }

        $this->line('');

        if ($errores === 0) {
            $this->comment('  La revisión de Meta suele tardar de unos minutos a 24 h.');
            $this->comment('  Revisa el avance con: php artisan whatsapp:plantillas');
            $this->line('');
        }

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function borrar(PlantillaService $plantillas, string $nombre): int
    {
        $respuesta = $plantillas->eliminar($nombre);

        $this->line('');

        if ($respuesta->successful()) {
            $this->info("  Plantilla «{$nombre}» eliminada de la WABA.");
            $this->line('');

            return self::SUCCESS;
        }

        $this->fallo($respuesta->status(), $respuesta->json());

        return self::FAILURE;
    }

    private function estado(string $status): string
    {
        return match ($status) {
            'APPROVED' => 'APROBADA',
            'PENDING'  => 'en revisión',
            'REJECTED' => 'RECHAZADA',
            'PAUSED'   => 'pausada',
            default    => $status,
        };
    }

    private function fallo(int $status, ?array $cuerpo): void
    {
        $this->line('');
        $this->error("Meta contestó HTTP {$status}");
        $this->line('  ' . data_get($cuerpo, 'error.message', 'sin detalle'));
        $this->pista((int) data_get($cuerpo, 'error.code'));
        $this->line('');
    }

    private function pista(int $codigo): void
    {
        match ($codigo) {
            190     => $this->comment('  Código 190: el access token caducó. Genera uno nuevo en el Paso 1 de Meta.'),
            100     => $this->comment('  Código 100: revisa el WABA_ID, o el token no tiene whatsapp_business_management.'),
            200     => $this->comment('  Código 200: al token le falta el permiso whatsapp_business_management.'),
            2388023 => $this->comment('  Ya existe una plantilla con ese nombre e idioma. Usa --forzar o bórrala antes.'),
            default => null,
        };
    }
}
