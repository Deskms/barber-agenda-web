<?php

namespace App\Console\Commands;

use App\Services\Agenda\Huecos;
use Illuminate\Console\Command;

class HuecosVencer extends Command
{
    protected $signature = 'huecos:vencer';

    protected $description = 'Marca como vencidos los avisos de huecos cuyo horario ya pasó';

    public function handle(Huecos $huecos): int
    {
        $this->info('Avisos vencidos: ' . $huecos->vencer());

        return self::SUCCESS;
    }
}
