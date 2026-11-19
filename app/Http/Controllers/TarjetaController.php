<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\Lealtad\TarjetaSellos;
use Illuminate\Support\Str;

/** Tarjeta pública de sellos. Se abre con el QR o la etiqueta NFC del mostrador. */
class TarjetaController extends Controller
{
    public function __invoke(string $token, TarjetaSellos $sellos)
    {
        abort_unless(preg_match('/^[a-f0-9]{40}$/', $token) === 1, 404);

        $cliente = Cliente::where('tarjeta_token', $token)->firstOrFail();

        return response()
            ->view('tarjeta', [
                'nombre' => $cliente->nombre ? (string) Str::of($cliente->nombre)->before(' ') : null,
                'estado' => $sellos->estado($cliente),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
