<?php

namespace App\Console\Commands;

use App\Models\Ajuste;
use App\Services\Marketing\Campanas;
use Illuminate\Console\Command;

/**
 * Escribe a quienes ya les toca volver (por ejemplo, 15 días desde su
 * último corte). Corre una vez al día y solo si la dueña lo prendió.
 */
class CampanasRegresos extends Command
{
    protected $signature = 'campanas:regresos {--forzar : Corre aunque esté apagado en el panel}';

    protected $description = 'Envía el mensaje de "ya te toca volver" a las clientas que corresponda';

    public function handle(Campanas $campanas): int
    {
        if (! Ajuste::activo('regreso_auto') && ! $this->option('forzar')) {
            $this->info('El recordatorio de regreso está apagado en el panel.');

            return self::SUCCESS;
        }

        $r = $campanas->enviarRegresos();

        $this->info("Gratis: {$r['gratis']} · Pagados: {$r['pagadas']} · Sin presupuesto: {$r['omitidas']}");

        return self::SUCCESS;
    }
}
