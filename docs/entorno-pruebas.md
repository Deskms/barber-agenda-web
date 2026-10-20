# Entorno de pruebas: citas.tunegociomx.app

La app corre en la laptop de pruebas, separada de TuNegocio_MX (que sigue en Apache/XAMPP):

| Pieza | Dónde |
|---|---|
| PHP | `C:\php84\php.exe` (Laravel 13 no corre en el PHP 8.2 de XAMPP) |
| Base de datos | MariaDB de XAMPP, base `barber_agenda`, usuario propio `barber_agenda` (sin acceso a otras bases) |
| Web | `php artisan serve` en `127.0.0.1:8010`, solo local |
| Publicación | Túnel de Cloudflare existente, hostname público `citas.tunegociomx.app` → `http://localhost:8010` |
| Cola | `php artisan queue:work` (respuestas del bot) |
| Programador | `php artisan schedule:work` (recordatorios) |

Arranque: `scripts\iniciar-windows.bat`.

## Conectar Meta

1. En la app de Meta, *WhatsApp > Configuración > Webhook*: URL `https://citas.tunegociomx.app/api/whatsapp/webhook`, token de verificación = `WHATSAPP_VERIFY_TOKEN`, suscribir el campo `messages`.
2. Copiar el *App Secret* (Configuración de la app > Básica) a `WHATSAPP_APP_SECRET`. Sin él, en producción el webhook rechaza todo.
3. Poner un token vigente en `WHATSAPP_TOKEN` (de preferencia de un usuario del sistema, que no caduca a las 24 h).
4. `php artisan config:cache` y reiniciar la cola.
5. `php artisan whatsapp:plantillas --crear` manda a revisión las plantillas de `config/whatsapp.php`; `php artisan whatsapp:plantillas` muestra su estado.

## Bot

- Flujo con botones y listas: servicio → día → hora → confirmar. El estado viaja en los ids de los botones, se revalida todo al recibirlo.
- Si hay `ANTHROPIC_API_KEY`, el texto libre ("¿tienes lugar el sábado a las 4 para corte?") se clasifica con IA para saltar pasos. La IA nunca reserva: solo propone y la clienta confirma.
- `BAJA` / `ALTA` apagan y prenden los avisos de espacios libres sin pasar por la IA.

## Pruebas

`C:\php84\php.exe artisan test` — 25 pruebas: agenda (bordes, traslapes, día cerrado, cancelación idempotente), webhook (firma, reintentos de Meta), bot (flujo completo, ids manipulados, cancelar cita ajena, IA simulada) y panel (sesión, difusión sin duplicados, recordatorio único).
