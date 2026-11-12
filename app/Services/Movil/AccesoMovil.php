<?php

namespace App\Services\Movil;

use App\Models\Cliente;
use App\Models\CodigoAcceso;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Entrada a la app móvil por código de WhatsApp.
 *
 * Las clientas no tienen contraseña: viven en la tabla clientes y se
 * identifican por su número. Mandar un código al chat que ya usan es más
 * seguro que inventarles una contraseña, y no agrega un dato más que cuidar.
 */
class AccesoMovil
{
    public const VIGENCIA_MINUTOS = 10;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * Normaliza a la forma que usa Meta: solo dígitos, con lada de país.
     * Un número mexicano de 10 dígitos se completa a 521XXXXXXXXXX.
     */
    public function normalizar(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';

        if (strlen($digitos) === 10) {
            return '521' . $digitos;
        }

        // 52 + 10 dígitos: Meta espera el 1 de México en medio.
        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            return '521' . substr($digitos, 2);
        }

        return $digitos;
    }

    /**
     * Crea el código y lo manda por WhatsApp.
     *
     * Devuelve el código solo cuando la app corre fuera de producción, para
     * que las pruebas automáticas y el equipo móvil puedan entrar sin
     * depender de que Meta entregue el mensaje.
     */
    public function enviarCodigo(string $telefono, ?string $ip = null): array
    {
        $waId = $this->normalizar($telefono);

        // La clienta se da de alta sola: el código llega a su WhatsApp, así
        // que solo quien tenga ese teléfono puede terminar de entrar.
        $cliente = Cliente::firstOrCreate(
            ['wa_id' => $waId],
            ['acepta_avisos' => false],
        );

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Los códigos anteriores de esa clienta dejan de servir.
        CodigoAcceso::query()
            ->where('cliente_id', $cliente->id)
            ->whereNull('usado_en')
            ->update(['usado_en' => Carbon::now()]);

        CodigoAcceso::create([
            'cliente_id'  => $cliente->id,
            'codigo_hash' => Hash::make($codigo),
            'expira_en'   => Carbon::now()->addMinutes(self::VIGENCIA_MINUTOS),
            'ip'          => $ip,
        ]);

        $this->avisar($cliente, $codigo);

        return [
            'cliente'  => $cliente,
            'devuelto' => app()->environment('production') ? null : $codigo,
        ];
    }

    /**
     * Canjea el código por un token. Devuelve null si no es válido, sin
     * decir por qué: distinguir "código incorrecto" de "número desconocido"
     * sirve para averiguar quién es clienta del negocio.
     */
    public function canjear(string $telefono, string $codigo): ?Cliente
    {
        $waId = $this->normalizar($telefono);

        $cliente = Cliente::where('wa_id', $waId)->first();

        if (! $cliente) {
            return null;
        }

        $registro = CodigoAcceso::query()
            ->where('cliente_id', $cliente->id)
            ->utilizables()
            ->latest('id')
            ->first();

        if (! $registro) {
            return null;
        }

        if (! Hash::check($codigo, $registro->codigo_hash)) {
            $registro->increment('intentos');

            return null;
        }

        $registro->forceFill(['usado_en' => Carbon::now()])->save();

        return $cliente;
    }

    /**
     * Fuera de la ventana de 24 h Meta solo deja iniciar con plantilla.
     * La plantilla codigo_acceso debe estar aprobada antes de usarse.
     */
    private function avisar(Cliente $cliente, string $codigo): void
    {
        try {
            if ($cliente->ventanaAbierta()) {
                $this->whatsapp->enviarTexto(
                    $cliente->wa_id,
                    "Tu código para entrar a la app es {$codigo}. Vence en "
                    . self::VIGENCIA_MINUTOS . ' minutos.',
                );

                return;
            }

            $this->whatsapp->enviarPlantilla($cliente->wa_id, 'codigo_acceso', 'es_MX', [$codigo]);
        } catch (\Throwable $e) {
            // Que falle el envío no debe tirar la petición: la clienta puede
            // volver a pedir el código. El código nunca se escribe en la bitácora.
            Log::channel('whatsapp')->error('No se pudo enviar el código de acceso', [
                'cliente_id' => $cliente->id,
                'mensaje'    => $e->getMessage(),
            ]);
        }
    }
}
