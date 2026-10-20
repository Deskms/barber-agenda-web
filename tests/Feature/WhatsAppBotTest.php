<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\EventoWhatsapp;
use App\Services\Agenda\CitaService;
use App\Services\Bot\InterpreteIA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppBotTest extends TestCase
{
    private const NUMERO = '5213339998877';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fingirMeta();
    }

    /** Simula un POST de Meta al webhook, firmado como lo firma Meta. */
    private function llega(array $mensaje, string $de = self::NUMERO, string $nombre = 'Karla López', ?string $firma = null)
    {
        $mensaje += ['from' => $de, 'id' => 'wamid.' . Str::random(12), 'timestamp' => (string) now()->timestamp];

        $cuerpo = json_encode(['object' => 'whatsapp_business_account', 'entry' => [[
            'id'      => '456',
            'changes' => [['field' => 'messages', 'value' => [
                'messaging_product' => 'whatsapp',
                'contacts'          => [['profile' => ['name' => $nombre], 'wa_id' => $de]],
                'messages'          => [$mensaje],
            ]]],
        ]]]);

        $firma ??= 'sha256=' . hash_hmac('sha256', $cuerpo, 'secreto-de-prueba');

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256'  => $firma,
        ], $cuerpo);
    }

    private function texto(string $texto, string $de = self::NUMERO)
    {
        return $this->llega(['type' => 'text', 'text' => ['body' => $texto]], $de);
    }

    private function toca(string $id, string $de = self::NUMERO)
    {
        return $this->llega(['type' => 'interactive', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => $id, 'title' => 'x']]], $de);
    }

    private function ultimo(): array
    {
        $enviados = $this->enviados();

        return end($enviados) ?: [];
    }

    /** Ids que el bot ofreció en el último mensaje interactivo. */
    private function opciones(): array
    {
        $i = $this->ultimo()['interactive'] ?? [];

        return isset($i['action']['buttons'])
            ? array_column(array_column($i['action']['buttons'], 'reply'), 'id')
            : array_column($i['action']['sections'][0]['rows'] ?? [], 'id');
    }

    // --- webhook ---------------------------------------------------------

    public function test_handshake_de_verificacion(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verificar-prueba&hub.challenge=12345')
            ->assertOk()->assertSee('12345');

        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_rechaza_webhook_sin_firma_valida(): void
    {
        $this->llega(['type' => 'text', 'text' => ['body' => 'hola']], firma: 'sha256=falsa')->assertForbidden();

        $this->assertSame(0, EventoWhatsapp::count());
        $this->assertSame([], $this->enviados());
    }

    public function test_registra_a_la_clienta_una_sola_vez_aunque_meta_reintente(): void
    {
        $mensaje = ['type' => 'text', 'text' => ['body' => 'hola'], 'id' => 'wamid.repetido'];

        $this->llega($mensaje)->assertOk();
        $this->llega($mensaje)->assertOk();

        $this->assertSame(1, Cliente::where('wa_id', self::NUMERO)->count());
        $this->assertSame('Karla López', Cliente::where('wa_id', self::NUMERO)->value('nombre'));
        $this->assertCount(1, $this->enviados(), 'El reintento no debe generar otra respuesta');
    }

    // --- flujo de reserva ------------------------------------------------

    public function test_reserva_completa_con_botones_y_listas(): void
    {
        $this->texto('hola');
        $this->assertSame(['menu:agendar', 'menu:citas', 'menu:info'], $this->opciones());
        $this->assertStringContainsString('Karla', $this->ultimo()['interactive']['body']['text']);

        $this->toca('menu:agendar');
        $corte = $this->corte();
        $this->assertContains("srv:{$corte->id}", $this->opciones());

        $this->toca("srv:{$corte->id}");
        $this->assertSame("dia:{$corte->id}:2026-10-20", $this->opciones()[0], 'Hoy martes hay lugar');

        $this->toca("dia:{$corte->id}:2026-10-20");
        $tabla = $this->enviados()[count($this->enviados()) - 2]['text']['body'];
        $this->assertStringContainsString('🟢 10:00 – 19:00  libre', $tabla);
        $this->assertStringContainsString('dura 1 h', $tabla);
        $this->assertSame("hora:{$corte->id}:2026-10-20T10:00", $this->opciones()[0]);
        $this->assertLessThanOrEqual(10, count($this->opciones()));

        $this->toca("hora:{$corte->id}:2026-10-20T10:00");
        $this->assertSame("conf:{$corte->id}:2026-10-20T10:00", $this->opciones()[0]);
        $this->assertSame(0, Cita::count(), 'Ver el horario no debe reservar');

        $this->toca("conf:{$corte->id}:2026-10-20T10:00");

        $cita = Cita::sole();
        $this->assertSame(Cita::ORIGEN_WHATSAPP, $cita->origen);
        $this->assertSame('2026-10-20 10:00', $cita->inicio->format('Y-m-d H:i'));
        $this->assertStringContainsString('confirmada', $this->ultimo()['text']['body']);
    }

    public function test_si_otra_persona_gana_el_horario_ofrece_alternativas(): void
    {
        $corte = $this->corte();
        app(CitaService::class)->agendar($this->clienta('Ana'), $corte, Carbon::parse('2026-10-20 10:00'));

        $this->toca("conf:{$corte->id}:2026-10-20T10:00");

        $this->assertSame(1, Cita::count());
        $this->assertNotContains("hora:{$corte->id}:2026-10-20T10:00", $this->opciones());
        $this->assertContains("hora:{$corte->id}:2026-10-20T11:00", $this->opciones());
    }

    public function test_ids_manipulados_no_rompen_ni_reservan(): void
    {
        foreach (['conf:999:2026-10-20T10:00', 'conf:1:2020-01-01T10:00', 'conf:1:hola', 'dia:1:2099-01-01', "srv:1' OR 1=1", 'cualquier:cosa'] as $id) {
            $this->toca($id)->assertOk();
        }

        $this->assertSame(0, Cita::count());
    }

    public function test_no_puede_cancelar_la_cita_de_otra_persona(): void
    {
        $ajena = app(CitaService::class)->agendar($this->clienta('Ana'), $this->corte(), Carbon::parse('2026-10-20 12:00'));

        $this->toca("cancelok:{$ajena->id}");

        $this->assertSame(Cita::CONFIRMADA, $ajena->fresh()->estado);
    }

    public function test_cancela_su_propia_cita_con_confirmacion(): void
    {
        $this->texto('hola');
        $karla = Cliente::where('wa_id', self::NUMERO)->sole();
        $cita  = app(CitaService::class)->agendar($karla, $this->corte(), Carbon::parse('2026-10-21 12:00'));

        $this->toca('menu:citas');
        $this->assertSame(["ver:{$cita->id}"], $this->opciones());

        $this->toca("cancelar:{$cita->id}");
        $this->assertSame(Cita::CONFIRMADA, $cita->fresh()->estado, 'Preguntar no debe cancelar');

        $this->toca("cancelok:{$cita->id}");
        $this->assertSame(Cita::CANCELADA, $cita->fresh()->estado);
    }

    public function test_baja_y_alta_de_avisos(): void
    {
        $this->texto('hola');
        $this->texto('BAJA');
        $this->assertFalse(Cliente::where('wa_id', self::NUMERO)->value('acepta_avisos'));

        $this->texto('alta');
        $this->assertTrue(Cliente::where('wa_id', self::NUMERO)->value('acepta_avisos'));
    }

    public function test_sin_ia_entiende_palabras_clave(): void
    {
        $this->texto('quiero una cita por favor');
        $this->assertContains("srv:{$this->corte()->id}", $this->opciones());

        $this->texto('cuanto cuesta el corte?');
        $this->assertStringContainsString('Corte de cabello', $this->ultimo()['interactive']['body']['text']);
    }

    public function test_con_ia_va_directo_a_confirmar_si_el_horario_esta_libre(): void
    {
        $corte = $this->corte();

        $this->mock(InterpreteIA::class, function ($m) use ($corte) {
            $m->shouldReceive('interpretar')->andReturn([
                'intencion' => 'agendar', 'servicio_id' => $corte->id, 'fecha' => '2026-10-22', 'hora' => '16:00',
            ]);
        });

        $this->texto('¿tienes lugar el jueves a las 4 para corte?');

        $this->assertSame("conf:{$corte->id}:2026-10-22T16:00", $this->opciones()[0]);
        $this->assertSame(0, Cita::count(), 'La IA nunca reserva por sí sola');
    }

    public function test_con_ia_si_la_hora_esta_ocupada_muestra_el_dia(): void
    {
        $corte = $this->corte();
        app(CitaService::class)->agendar($this->clienta('Ana'), $corte, Carbon::parse('2026-10-22 16:00'));

        $this->mock(InterpreteIA::class, fn ($m) => $m->shouldReceive('interpretar')->andReturn([
            'intencion' => 'agendar', 'servicio_id' => $corte->id, 'fecha' => '2026-10-22', 'hora' => '16:00',
        ]));

        $this->texto('el jueves a las 4 para corte');

        $this->assertStringContainsString('no está disponible', $this->enviados()[0]['text']['body']);
        $this->assertNotContains("hora:{$corte->id}:2026-10-22T16:00", $this->opciones());
    }
}
