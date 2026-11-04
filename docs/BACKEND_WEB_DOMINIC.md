# Backend WEB — Barber Agenda

**Responsable:** Dominic (Adrián Dominic Hernández Esqueda) · **Rama:** `feature/backend-web-dominic` · **Fecha:** 3 de noviembre de 2026

Este documento describe el backend que consume el frontend web de Franc. Los contratos para el resto del equipo están en:

- [INTEGRACION_FRONTEND_WEB_FRANC.md](INTEGRACION_FRONTEND_WEB_FRANC.md) — frontend web (Franc).
- [MATRIZ_JIRA_IMPLEMENTACION.md](MATRIZ_JIRA_IMPLEMENTACION.md) — estado real de cada historia de Jira.
- `../android/INTEGRACION_BACKEND_MOVIL_JOSE.md` y `../android/INTEGRACION_FRONTEND_MOVIL_RODRI.md` — fuera de este repositorio, en la carpeta `android/` de la entrega.

---

## 1. Arquitectura

Monolito modular en **Laravel 13.31 sobre PHP 8.4**, como define `docs/arquitectura.md`. La API es el único componente que escribe en la base de datos; el panel web, WhatsApp y la app móvil solo envían intenciones.

```
Frontend web (Franc) ──HTTP/JSON + Bearer──►  /api/web/*      ─┐
WhatsApp (Meta Cloud API) ──webhook────────►  /api/whatsapp/* ─┼─► Servicios de dominio ─► PostgreSQL
Backend móvil (José) ──(sus propios endpoints, por coordinar)──┘   (Agenda, Difusión)
```

Capas:

| Capa | Ubicación | Qué hace |
|---|---|---|
| Rutas | `routes/api.php` | Prefijo `/api/web`, middleware `auth:sanctum`, `activo` y `rol:admin` / `rol:cliente`. |
| Controladores | `app/Http/Controllers/Api/Web/` | Validan la entrada, llaman a los servicios y devuelven recursos JSON. No calculan disponibilidad. |
| Recursos JSON | `app/Http/Resources/` | Formato estable de salida; fechas ISO 8601 con desfase de `America/Mexico_City`. |
| Servicios de dominio | `app/Services/Agenda/`, `app/Services/Difusion/`, `app/Services/WhatsApp/` | Reglas de negocio: disponibilidad, citas, huecos, difusión y envío a Meta. |
| Eventos | `app/Events/EspacioLiberado.php` y `app/Listeners/` | Una cancelación o reprogramación registra el hueco sin acoplar la agenda a WhatsApp. |
| Políticas y middleware | `app/Policies/CitaPolicy.php`, `app/Http/Middleware/` | La dueña ve todo; una clienta, solo lo suyo. Una cuenta desactivada deja de servir. |

## 2. Estructura del backend

```
app/
  Console/Commands/      AgendaDemo, WhatsAppEnviar, WhatsAppPlantillas (laboratorio), CrearAdmin (nuevo)
  Events/                EspacioLiberado
  Exceptions/            HorarioNoDisponibleException (409), CitaNoModificableException (422), WhatsAppNoConfiguradoException (503)
  Http/Controllers/Api/  WhatsAppWebhookController
  Http/Controllers/Api/Web/  Auth, Usuario, Servicio, Horario, Bloqueo, Disponibilidad, Cita, Agenda, Cliente, MiCuenta, Hueco
  Http/Middleware/       EnsureRol, EnsureUsuarioActivo
  Http/Resources/        User, Cliente, Servicio, Cita, Bloqueo, Horario, Hueco, Fecha
  Listeners/             BuscarClientasParaEspacioLiberado (laboratorio), RegistrarEspacioLiberado (nuevo)
  Models/                User, Cliente, Servicio, ServicioEtapa, Cita, CitaBloque, HorarioAtencion,
                         BloqueoAgenda, Hueco, Difusion, EventoWhatsapp
  Policies/              CitaPolicy
  Services/Agenda/       DisponibilidadService, CitaService, HuecoService, ResultadoDisponibilidad
  Services/Difusion/     DifusionService
  Services/WhatsApp/     WhatsAppService, PlantillaService (laboratorio)
  Support/               Telefono (normalización a wa_id)
config/agenda.php        Reglas configurables de la agenda
config/cors.php          Orígenes del frontend web
lang/es/validation.php   Mensajes de validación en español
tests/Feature/Web/       Pruebas de la API web
docs/evidencias/         Salida real de pruebas e integración HTTP
```

## 3. Módulos desarrollados

| Módulo | Estado | Notas |
|---|---|---|
| Autenticación y usuarios | Terminado | Registro de clientas, login, logout, perfil, roles, desactivación de cuentas, administración de usuarios y comando `usuarios:crear-admin`. |
| Servicios | Terminado | CRUD con precio, duración y etapas; eliminar = desactivar. |
| Horarios de atención | Terminado | Un intervalo por día de la semana. |
| Bloqueos de agenda | Terminado | Fechas u horarios sin atención; avisa de citas afectadas sin cancelarlas. |
| Disponibilidad | Terminado | Agenda flexible por etapas (sección 6). |
| Citas | Terminado | Crear, consultar, reprogramar, cancelar y registrar la atención (completada o no asistió). |
| Agenda del panel | Terminado | Vista por día o semana y sondeo de cambios cada 30 s. |
| Clientas | Terminado | CRUD, consentimiento de avisos, historial de visitas e historial de conversación. |
| Huecos y difusión | Terminado en código, sin envío real | Lista de huecos, candidatas elegibles y difusión manual. El envío real depende de la aprobación de la plantilla `espacio_liberado` en Meta (SCRUM-12). |
| Webhook de WhatsApp | Portado del laboratorio | Ahora enlaza cada mensaje con su clienta. |
| Flujo conversacional por WhatsApp | No implementado | Ver pendientes. |
| Puntos y recompensas | No implementado | No existe en Jira (ver decisiones pendientes). |

## 4. Base de datos

Motor objetivo: **PostgreSQL 16** (Jira SCRUM-15). El código no usa funciones exclusivas de un motor: la suite pasa igual en SQLite (desarrollo rápido) y en PostgreSQL.

| Tabla | Para qué | Columnas clave |
|---|---|---|
| `users` | Cuentas de acceso | `rol` (`admin` = dueña, `cliente`), `activo` |
| `clientes` | Clientas, tengan o no cuenta | `wa_id` (único), `nombre`, `acepta_avisos` (inicia en **falso**), `user_id` (único, nulo si solo usa WhatsApp) |
| `servicios` | Catálogo | `duracion_minutos` (suma de etapas), `precio`, `activo` |
| `servicio_etapas` | Etapas de cada servicio | `orden`, `nombre`, `duracion_minutos`, `ocupa_estilista` |
| `horarios_atencion` | Horario por día | `dia_semana` (0 = domingo … 6 = sábado), `abre`, `cierra`, `activo` |
| `bloqueos_agenda` | Días u horas sin atención | `inicio`, `fin`, `motivo`, `user_id` |
| `citas` | Reservas | `cliente_id`, `servicio_id`, `inicio`, `fin`, `estado`, `origen`, `notas`, `cancelada_en`, `reprogramada_en`, `atendida_en` |
| `cita_bloques` | Copia de las etapas con horas reales al agendar | `cita_id`, `orden`, `inicio`, `fin`, `ocupa_estilista` |
| `espacios_liberados` | Huecos por cancelación o reprogramación | `cita_id`, `servicio_id`, `inicio`, `fin`, `motivo` |
| `difusiones` | Cada aviso enviado | `espacio_liberado_id` + `cliente_id` (únicos juntos), `estado_envio`, `wamid`, `error` |
| `eventos_whatsapp` | Mensajes y estados de Meta | `wamid` (único), `cliente_id`, `direccion` (`entrante`/`saliente`), `payload` |
| `personal_access_tokens` | Tokens de Sanctum | — |

Relaciones: una clienta tiene muchas citas, difusiones y eventos; un usuario tiene como máximo una clienta; un servicio tiene muchas etapas y citas; una cita tiene muchos bloques y puede originar huecos; un hueco tiene muchas difusiones.

**Diferencias con el documento de base de datos entregado antes** (que se escribió antes de que existiera el código): los estados de cita son `confirmada`, `cancelada`, `completada` y `no_asistio` (el documento decía `agendada`); `dia_semana` usa 0–6 como Carbon (el documento decía ISO 1–7); y la restricción de exclusión de PostgreSQL contra traslapes **ya no aplica**, porque ahora dos citas sí pueden traslaparse si sus etapas activas no chocan. La protección contra dobles reservas vive en la transacción con bloqueo (sección 6.4).

## 5. Autenticación

- **Sanctum con tokens Bearer** (`Authorization: Bearer <token>`). No hay cookies ni CSRF porque el frontend web está en otro origen.
- `POST /api/web/auth/registro` crea una cuenta de **clienta**. Si su teléfono ya existe porque escribió por WhatsApp, se vincula a esa clienta en lugar de duplicarla.
- La cuenta de la **dueña** se crea con `php artisan usuarios:crear-admin correo@ejemplo.com --nombre="Joss"` (pide la contraseña) o desde `POST /api/web/admin/usuarios`.
- `POST /api/web/auth/logout` revoca solo el token usado. Desactivar una cuenta revoca todos sus tokens.
- Límite de intentos: login 6 por minuto y registro 10 por minuto por IP.

## 6. Reglas de negocio de la agenda

### 6.1 Agenda flexible por etapas

Cada servicio se divide en etapas. Solo las etapas con `ocupa_estilista = true` bloquean a la estilista; durante una etapa de procesamiento (un tinte reposando) puede atender a otra clienta. Un servicio sin etapas se comporta como una sola etapa activa.

Ejemplo del martes, verificado en pruebas y por HTTP:

| Cita | Bloques | Resultado |
|---|---|---|
| Tinte 9:00–12:00 | Aplicación 9:00–9:30 (activa) · Procesamiento 9:30–11:30 · Enjuague 11:30–12:00 (activa) | 201 |
| Corte de hombre 9:30–10:30 | Activa todo el tiempo | 201: cae en el procesamiento |
| Corte de hombre 10:30–11:30 | Activa todo el tiempo | 201: cae en el procesamiento |
| Corte de hombre 11:00–12:00 | Activa todo el tiempo | **409**: choca con el enjuague |

### 6.2 Validaciones de cada reserva (en este orden)

1. No está en el pasado (`en_el_pasado`).
2. Hay horario de atención ese día (`dia_sin_atencion`).
3. La cita completa cabe en el horario (`fuera_de_horario`).
4. Ningún bloqueo toca el intervalo completo de la cita (`bloqueado`). La clienta sigue en el local durante el procesamiento, así que el local debe estar abierto.
5. Ninguna etapa activa nueva se traslapa con una etapa activa existente (`estilista_ocupada`). Los traslapes son estrictos: 10:00–11:00 y 11:00–12:00 no chocan.
6. Si se configuró un máximo de clientas simultáneas, no se rebasa (`capacidad_llena`).

### 6.3 Configuración (`config/agenda.php` y `.env`)

| Variable | Valor por defecto | Efecto |
|---|---|---|
| `AGENDA_PASO_MINUTOS` | 15 | Cada cuánto se ofrece un inicio de cita |
| `AGENDA_MAX_CLIENTAS_SIMULTANEAS` | vacío (sin límite) | Sillas o lavabos disponibles |
| `AGENDA_ALTERNATIVAS` | 3 | Alternativas que se ofrecen en un 409 |
| `AGENDA_DIAS_BUSQUEDA` | 14 | Días hacia adelante para buscar alternativas |

### 6.4 Concurrencia

`CitaService` revalida la disponibilidad **dentro** de la transacción y bloquea con `SELECT … FOR UPDATE` la fila del horario del día. Dos reservas simultáneas del mismo día se atienden en turno, y la segunda ve la cita de la primera. Verificado en PostgreSQL con 10 procesos simultáneos: una reserva confirmada y nueve rechazadas (`docs/evidencias/pruebas_postgresql.txt`).

### 6.5 Otras reglas

- Las citas guardan una copia de sus etapas (`cita_bloques`). Si la dueña edita un servicio, las citas ya agendadas no cambian.
- Cancelar es idempotente y solo aplica a citas confirmadas. Una clienta solo cancela citas que aún no empiezan; la dueña puede cancelar cualquier cita confirmada.
- Reprogramar mueve la misma cita; el horario que deja se registra como hueco.
- Una cita completada cuenta como **visita** en el historial.
- Un bloqueo no cancela citas existentes: las devuelve en `citas_afectadas` para que la dueña decida.
- La difusión de un hueco nunca es automática. Solo se envía a clientas con consentimiento, que no liberaron el hueco, sin cita traslapada y que no lo recibieron antes. Si el hueco ya se ocupó, responde 409.
- Los teléfonos se normalizan al formato `wa_id` de WhatsApp: `3312345678` → `5213312345678` (`app/Support/Telefono.php`). Así la web y el webhook no duplican clientas.

## 7. Endpoints

Base: `/api/web`. Detalle de cuerpos, respuestas y errores en [INTEGRACION_FRONTEND_WEB_FRANC.md](INTEGRACION_FRONTEND_WEB_FRANC.md).

| Método | Ruta | Acceso | Uso |
|---|---|---|---|
| POST | `/auth/registro` | Público | Registro de clienta |
| POST | `/auth/login` | Público | Inicio de sesión |
| POST | `/auth/logout` | Autenticado | Cierra la sesión actual |
| GET | `/auth/me` | Autenticado | Usuario actual |
| GET | `/servicios`, `/servicios/{id}` | Público | Catálogo activo |
| GET | `/horarios` | Público | Horario semanal |
| GET | `/disponibilidad?servicio_id=&fecha=` | Público | Horarios libres |
| GET / POST | `/citas` | Autenticado | Listar (propias o todas) y crear |
| GET / PATCH | `/citas/{id}` | Dueña o propietaria | Ver y reprogramar |
| POST | `/citas/{id}/cancelar` | Dueña o propietaria | Cancelar |
| GET | `/mi/historial` | Clienta | Visitas y citas propias |
| GET / POST / PATCH | `/admin/usuarios[/{id}]` | Dueña | Usuarios |
| GET / POST / PUT / DELETE | `/admin/servicios[/{id}]` | Dueña | Servicios |
| PUT | `/admin/horarios/{dia}` | Dueña | Horario de un día |
| GET / POST / DELETE | `/admin/bloqueos[/{id}]` | Dueña | Bloqueos |
| GET / POST / PATCH | `/admin/clientes[/{id}]` | Dueña | Clientas |
| GET | `/admin/clientes/{id}/historial` y `/mensajes` | Dueña | Historial y conversación |
| GET | `/admin/agenda`, `/admin/agenda/cambios` | Dueña | Agenda y sondeo |
| PATCH | `/admin/citas/{id}/estado` | Dueña | Completada o no asistió |
| GET | `/admin/huecos`, `/admin/huecos/{id}/candidatas` | Dueña | Huecos y candidatas |
| POST | `/admin/huecos/{id}/difundir` | Dueña | Difusión por WhatsApp |
| GET / POST | `/api/whatsapp/webhook` | Meta | Verificación y eventos |

## 8. Configuración y ejecución

En Windows, con el PHP 8.4 del laboratorio (`set PATH=C:\php84;%PATH%`):

```bat
cd backend-web
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate --seed
php artisan usuarios:crear-admin duena@ejemplo.com --nombre="Joss"
php artisan serve
```

Para PostgreSQL, en el `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=barber_agenda
DB_USERNAME=...
DB_PASSWORD=...
```

Variables nuevas en `.env.example`: `APP_TIMEZONE`, `CORS_ALLOWED_ORIGINS`, `AGENDA_*`, `WHATSAPP_NUMERO_PRUEBA`, `ADMIN_EMAIL` y `ADMIN_PASSWORD`, todas vacías o con valores de desarrollo. El `WHATSAPP_VERIFY_TOKEN` del laboratorio venía con un valor real en `.env.example`; aquí quedó vacío. **Conviene generar uno nuevo**, porque el anterior ya estuvo en un archivo de ejemplo.

## 9. Pruebas realizadas

| Prueba | Resultado | Evidencia |
|---|---|---|
| Suite completa en SQLite en memoria | **57 pruebas, 333 aserciones, todas pasan** | `docs/evidencias/pruebas_sqlite.txt` |
| Suite completa en PostgreSQL 16.15 | **57 pruebas, todas pasan** | `docs/evidencias/pruebas_postgresql.txt` |
| Concurrencia: 10 procesos, misma hora | 1 confirmada, 9 rechazadas | `docs/evidencias/pruebas_postgresql.txt` |
| `migrate:rollback` completo en PostgreSQL | 0 tablas restantes y vuelve a migrar | `docs/evidencias/pruebas_postgresql.txt` |
| Integración HTTP real con `php artisan serve` | Login, CORS, catálogo, ejemplo del martes, 401 y datos guardados | `docs/evidencias/prueba_http_integracion.txt` |
| Estilo con Laravel Pint | Sin observaciones | `vendor/bin/pint --test` |

Cobertura de la suite: registro (incluida la vinculación con una clienta de WhatsApp), login correcto y fallido, logout con revocación, cuentas desactivadas, permisos de dueña y clienta, rutas sin token (401), CRUD de servicios con etapas, desactivación, disponibilidad (día libre, cerrado, lleno, pasado y con tinte), citas compatibles e incompatibles, dos tintes traslapados, horario, pasado, capacidad, cancelación propia y ajena, idempotencia, reprogramación, registro de visitas, bloqueos, agenda y sondeo, horarios, clientas y consentimiento, historial, conversación desde el webhook, huecos, candidatas, difusión (enviada, fallida, repetida, sin configuración y hueco ocupado) y normalización de teléfonos.

Las pruebas de WhatsApp usan respuestas simuladas de Meta (`Http::fake`). **No se envió ningún mensaje real.**

Para correrlas: `php artisan test` (con `PAO_DISABLE=1` si la salida aparece en formato JSON).

## 10. Pendientes conocidos

| Pendiente | Por qué |
|---|---|
| Flujo conversacional por WhatsApp (SCRUM-28, 32, 33, 34, 38, 41) | Fuera del alcance del backend web; depende de las plantillas aprobadas (SCRUM-12). Se puede reutilizar `CitaService`. |
| Traducir errores de Meta 190, 131047 y 131030 a excepciones (SCRUM-17) | `WhatsAppService` del laboratorio registra el error en el log, pero no lo traduce. |
| Sub-bloques de un hueco (SCRUM-31) y toma parcial (SCRUM-41) | No implementado. Hoy el hueco se ofrece con su servicio original. |
| Recordatorios automáticos (SCRUM-43) | No implementado. |
| Costo estimado de una difusión (criterio de SCRUM-35) | Falta definir la tarifa de Meta por mensaje de marketing. |
| Pipeline de CI en GitHub Actions (SCRUM-30) | El código ya pasa Pint y las pruebas; el pipeline es tarea de Franc. |
| Docker y staging con TLS (SCRUM-14, 44) | Infraestructura, fuera de esta entrega. |
| Mensajes salientes que no son difusiones en el historial | Solo se guardan los avisos de huecos. Las confirmaciones futuras deberán guardarse igual. |

## 11. Decisiones pendientes (no inventé estas reglas)

1. **Etapas reales de cada servicio.** El tinte del seeder (30 + 120 + 30 min) es un ejemplo. La dueña debe confirmar las duraciones y qué etapas la ocupan.
2. **Máximo de clientas simultáneas.** Sin límite por ahora. ¿Cuántas sillas y lavabos hay?
3. **Bloqueos durante el procesamiento.** Hoy un bloqueo impide cualquier cita que lo toque, aunque sea en la etapa de espera. ¿Es correcto?
4. **Política de cancelación.** No hay anticipación mínima. ¿Se puede cancelar a cualquier hora?
5. **Puntos y recompensas.** Aparecen en la propuesta de la app móvil, pero no hay historia en Jira ni reglas (cuántos puntos por visita, qué recompensas). No se implementó.
6. **Registro con correo o solo teléfono.** La web pide correo y contraseña; SCRUM-58 describe un registro móvil con nombre y teléfono. Hay que acordarlo con José para que una clienta use la misma cuenta en ambos.
7. **Asignaciones de Jira.** No coinciden con la división oficial del equipo. Ver la matriz.

## 12. Glosario mínimo

**wa_id:** número de WhatsApp de la clienta en formato internacional sin `+`. **wamid:** identificador único de un mensaje en Meta. **Etapa activa:** tiempo en que la estilista atiende a la clienta. **Procesamiento:** tiempo de espera en que la estilista queda libre. **Hueco:** horario liberado por una cancelación o reprogramación. **Difusión:** aviso de un hueco enviado por WhatsApp a una clienta.
