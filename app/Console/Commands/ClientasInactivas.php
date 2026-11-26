<?php

namespace App\Console\Commands;

use App\Services\Clientes\Clientas;
use Illuminate\Console\Command;

class ClientasInactivas extends Command
{
    protected $signature = 'clientas:inactivas';

    protected $description = 'Marca inactivas a las clientas sin visitas en 20 días y reactiva a las que volvieron a agendar';

    public function handle(Clientas $clientas): int
    {
        [$marcadas, $reactivadas] = $clientas->actualizarInactivas();
        $this->info("Inactivas nuevas: {$marcadas}. Reactivadas: {$reactivadas}.");

        return self::SUCCESS;
    }
}
