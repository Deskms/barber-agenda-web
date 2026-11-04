# Backend WEB — Barber Agenda

**Responsable:** Dominic (Adrián Dominic Hernández Esqueda) · **Rama:** `feature/backend-web-dominic` · **Fecha:** 3 de noviembre de 2026

Barber Agenda tiene dos canales: la **página web** (frontend de Franc sobre este backend) y la **app Android** (frontend de Rodri sobre el backend móvil de José). **El sistema no usa WhatsApp.**

Este documento describe el backend que consume el frontend web de Franc. Los contratos para el resto del equipo están en:

- [INTEGRACION_FRONTEND_WEB_FRANC.md](INTEGRACION_FRONTEND_WEB_FRANC.md) — frontend web (Franc).
- [MATRIZ_JIRA_IMPLEMENTACION.md](MATRIZ_JIRA_IMPLEMENTACION.md) — estado real de cada historia de Jira.
- `../android/INTEGRACION_BACKEND_MOVIL_JOSE.md` y `../android/INTEGRACION_FRONTEND_MOVIL_RODRI.md` — fuera de este repositorio, en la carpeta `android/` de la entrega.

---

## 1. Arquitectura

Monolito modular en **Laravel 13.31 sobre PHP 8.4**. La API es el único componente que escribe en la base de datos; la web y la app solo envían peticiones.

```
Página web (Franc) ──HTTP/JSON + Bearer──►  /api/web/*  ─┐
                                                          ├─► Servicios de dominio (Agenda) ─► PostgreSQL
App Android (Rodri) ──► backend móvil (José, por definir) ┘
```

| Capa | Ubicación | Qué hace |
|---|---|---|
| Rutas | `routes/api.php` | Prefijo `/api/web`, middleware `auth:sanctum`, `activo` y `rol:admin` / `rol:cliente`. |
| Controladores | `app/Http/Controllers/Api/Web/` | Validan la entrada, llaman a los servicios y devuelven recursos JSON. No calculan disponibilidad. |
| Recursos JSON | `app/Http/Resources/` | Formato estable de salida; fechas ISO 8601 con desfase de `America/Mexico_City`. |
| Servicios de dominio | `app/Services/Agenda/` | Reglas de negocio: disponibilidad, citas y huecos. |
| Eventos | `app/Events/EspacioLiberado.php`, `app/Listeners/RegistrarEspacioLiberado.php` | Una cancelación o reprogramación registra el hueco para las citas de última hora. |
| Políticas y middleware | `app/Policies/CitaPolicy.php`, `app/Http/Middleware/` | La dueña ve todo; una clienta, solo lo suyo. Una cuenta desactivada deja de servir. |

## 2. Estructura del backend

```
app/
  Console/Commands/      CrearAdmin (usuarios:crear-admin)
  Events/                EspacioLiberado
  Exceptions/            HorarioNoDisponibleException (409), CitaNoModificableException (422)
  Http/Controllers/Api/Web/  Auth, Usuario, Servicio, Horario, Bloqueo, Disponibilidad, Cita, Agenda, Cliente, MiCuenta, Hueco
  Http/Middleware/       EnsureRol, EnsureUsuarioActivo
  Http/Resources/        User, Cliente, Servicio, Cita, Bloqueo, Horario, Hueco, Fecha
  Listeners/             RegistrarEspacioLiberado
  Models/                User, Cliente, Servicio, ServicioEtapa, Cita, CitaBloque, HorarioAtencion, BloqueoAgenda, Hueco
  Policies/              CitaPolicy
  Services/Agenda/       DisponibilidadService, CitaService, HuecoService, ResultadoDisponibilidad
  Support/               Telefono (normalización de teléfonos)
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
| Clientas | Terminado | CRUD, consentimiento de avisos e historial de visitas. |
| Huecos (citas de última hora) | Terminado (lado servidor) | Lista de huecos con `sigue_libre` y candidatas elegibles. El aviso a la clienta lo dará la app Android; falta acordarlo con José y Rodri. |
| Puntos y recompensas | No implementado | No existe en Jira (ver decisiones pendientes). |

## 4. Base de datos

Motor objetivo: **PostgreSQL 16** (Jira SCRUM-15). El código no usa funciones exclusivas de un motor: la suite pasa igual en SQLite (desarrollo rápido) y en PostgreSQL.

| Tabla | Para qué | Columnas clave |
|---|---|---|
| `users` | Cuentas de acceso | `rol` (`admin` = dueña, `cliente`), `activo` |
| `clientes` | Clientas, tengan o no cuenta | `telefono` (único, 10 dígitos en México), `nombre`, `acepta_avisos` (inicia en **falso**), `user_id` (único; nulo si la registró la dueña y aún no tiene cuenta) |
| `servicios` | Catálogo | `duracion_minutos` (suma de etapas), `precio`, `activo` |
| `servicio_etapas` | Etapas de cada servicio | `orden`, `nombre`, `duracion_minutos`, `ocupa_estilista` |
| `horarios_atencion` | Horario por día | `dia_semana` (0 = domingo … 6 = sábado), `abre`, `cierra`, `activo` |
| `bloqueos_agenda` | Días u horas sin atención | `inicio`, `fin`, `motivo`, `user_id` |
| `citas` | Reservas | `cliente_id`, `servicio_id`, `inicio`, `fin`, `estado`, `origen` (`web`, `panel`, `app`), `notas`, `cancelada_en`, `reprogramada_en`, `atendida_en` |
| `cita_bloques` | Copia de las etapas con horas reales al agendar | `cita_id`, `orden`, `inicio`, `fin`, `ocupa_estilista` |
| `espacios_liberados` | Huecos por cancelación o reprogramación | `cita_id`, `servicio_id`, `inicio`, `fin`, `motivo` |
| `personal_access_tokens` | Tokens de Sanctum | — |

Relaciones: una clienta tiene muchas citas; un usuario tiene como máximo una clienta; un servicio tiene muchas etapas y citas; una cita tiene muchos bloques y puede originar huecos.

**Diferencias con el documento de base de datos entregado antes:** ese documento incluía tablas de WhatsApp (`eventos_whatsapp`, `difusiones`, `conversaciones`) que **ya no existen**, porque el sistema no usa WhatsApp. Los estados de cita son `confirmada`, `cancelada`, `completada` y `no_asistio`; `dia_semana` usa 0–6, y la restricción de exclusión contra traslapes ya no aplica, porque dos citas pueden traslaparse si sus etapas activas no chocan.

## 5. Autenticación

- **Sanctum con tokens Bearer** (`Authorization: Bearer <token>`). No hay cookies ni CSRF porque el frontend web está en otro origen.
- `POST /api/web/auth/registro` crea una cuenta de **clienta**. Si la dueña ya la había registrado con ese teléfono, se vincula a esa clienta en lugar de duplicarla.
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
| Corte de hombre 9:30–10:30 | Activa | 201: cae en el procesamiento |
| Corte de hombre 10:30–11:30 | Activa | 201: cae en el procesamiento |
| Corte de hombre 11:00–12:00 | Activa | **409**: choca con el enjuague |

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

`CitaService` revalida la disponibilidad **dentro** de la transacción y bloquea con `SELECT … FOR UPDATE` la fila del horario del día. Verificado en PostgreSQL con 10 procesos simultáneos sobre la misma hora: una reserva confirmada y nueve rechazadas.

### 6.5 Otras reglas

- Las citas guardan una copia de sus etapas (`cita_bloques`). Editar un servicio no cambia las citas ya agendadas.
- Cancelar es idempotente y solo aplica a citas confirmadas. Una clienta solo cancela citas que aún no empiezan; la dueña puede cancelar cualquier cita confirmada.
- Reprogramar mueve la misma cita; el horario que deja se registra como hueco.
- Una cita completada cuenta como **visita** en el historial.
- Un bloqueo no cancela citas existentes: las devuelve en `citas_afectadas` para que la dueña decida.
- **Huecos (citas de última hora):** al cancelar o reprogramar se registra el hueco. `sigue_libre` indica si el servicio original todavía cabe. Las candidatas son clientas con consentimiento, que no liberaron el hueco y sin cita traslapada.
- **Teléfonos:** en México se guardan 10 dígitos. `33 1234 5678`, `+52 33 1234 5678` y `+52 1 33 1234 5678` quedan como `3312345678` (`app/Support/Telefono.php`). Así la misma clienta no se duplica entre la web y la app.

## 7. Endpoints

Base: `/api/web`, con 35 rutas en total. Detalle de cuerpos, respuestas y errores en [INTEGRACION_FRONTEND_WEB_FRANC.md](INTEGRACION_FRONTEND_WEB_FRANC.md).

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
| GET | `/admin/clientes/{id}/historial` | Dueña | Historial de visitas |
| GET | `/admin/agenda`, `/admin/agenda/cambios` | Dueña | Agenda y sondeo |
| PATCH | `/admin/citas/{id}/estado` | Dueña | Completada o no asistió |
| GET | `/admin/huecos`, `/admin/huecos/{id}/candidatas` | Dueña | Huecos y candidatas |

## 8. Configuración y ejecución

En Windows, con PHP 8.4 (`set PATH=C:\php84;%PATH%`):

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

Variables nuevas en `.env.example`: `APP_TIMEZONE`, `CORS_ALLOWED_ORIGINS`, `AGENDA_*`, `ADMIN_EMAIL` y `ADMIN_PASSWORD`. Se retiraron todas las variables de WhatsApp.

## 9. Pruebas realizadas

| Prueba | Resultado | Evidencia |
|---|---|---|
| Suite completa en SQLite en memoria | **52 pruebas, 300 aserciones, todas pasan** | `docs/evidencias/pruebas_sqlite.txt` |
| Suite completa en PostgreSQL 16.15 | **52 pruebas, todas pasan** | `docs/evidencias/pruebas_postgresql.txt` |
| Concurrencia: 10 procesos a la misma hora | 1 confirmada, 9 rechazadas | `docs/evidencias/pruebas_postgresql.txt` |
| `migrate:fresh --seed` y `migrate:rollback` en PostgreSQL | Correctos; 0 tablas restantes tras el rollback | `docs/evidencias/pruebas_postgresql.txt` |
| Integración HTTP real con `php artisan serve` | Login, CORS, catálogo, ejemplo del martes, 401 y datos guardados | `docs/evidencias/prueba_http_integracion.txt` |
| Estilo con Laravel Pint | Sin observaciones | `vendor/bin/pint --test` |

Cobertura: registro (incluida la vinculación con una clienta ya registrada por la dueña), login correcto y fallido, logout con revocación, cuentas desactivadas, permisos de dueña y clienta, rutas sin token (401), CRUD de servicios con etapas, desactivación, disponibilidad (día libre, cerrado, lleno, pasado y con tinte), citas compatibles e incompatibles, dos tintes traslapados, horario, pasado, capacidad, cancelación propia y ajena, idempotencia, reprogramación, registro de visitas, bloqueos, agenda y sondeo, horarios, clientas y consentimiento, historial, huecos (lista, `sigue_libre` y candidatas) y normalización de teléfonos.

Para correrlas: `php artisan test` (con `PAO_DISABLE=1` si la salida aparece en formato JSON).

## 10. Pendientes conocidos

| Pendiente | Por qué |
|---|---|
| Aviso de citas de última hora a las clientas | El servidor ya registra huecos y candidatas. Falta acordar con José y Rodri cómo lo muestra la app: notificación push o una pantalla de "huecos disponibles". |
| Sub-bloques de un hueco (SCRUM-31) | No implementado. Hoy el hueco se ofrece con su servicio original. |
| Recordatorios de cita | No implementado. Sin WhatsApp, sería una notificación de la app o un correo. |
| Pipeline de CI en GitHub Actions (SCRUM-30) | El código ya pasa Pint y las pruebas; el pipeline es tarea de Franc. |
| Docker y staging con TLS (SCRUM-14, 44) | Infraestructura, fuera de esta entrega. |

## 11. Decisiones pendientes (no inventé estas reglas)

1. **Etapas reales de cada servicio.** El tinte del seeder (30 + 120 + 30 min) es un ejemplo. La dueña debe confirmar las duraciones y qué etapas la ocupan.
2. **Máximo de clientas simultáneas.** Sin límite por ahora. ¿Cuántas sillas y lavabos hay?
3. **Bloqueos durante el procesamiento.** Hoy un bloqueo impide cualquier cita que lo toque, aunque sea en la etapa de espera. ¿Es correcto?
4. **Política de cancelación.** No hay anticipación mínima. ¿Se puede cancelar a cualquier hora?
5. **Puntos y recompensas.** Aparecen en la propuesta de la app móvil, pero no hay historia en Jira ni reglas (cuántos puntos por visita, qué recompensas). No se implementó.
6. **Registro con correo o solo teléfono.** La web pide correo y contraseña; SCRUM-58 describe un registro móvil con nombre y teléfono. Hay que acordarlo con José para que una clienta use la misma cuenta en ambos canales.
7. **Jira.** Hay épicas completas de WhatsApp que ya no aplican, y las asignaciones no coinciden con la división oficial del equipo. Ver la matriz.

## 12. Glosario mínimo

**Etapa activa:** tiempo en que la estilista atiende a la clienta. **Procesamiento:** tiempo de espera en que la estilista queda libre. **Hueco:** horario liberado por una cancelación o reprogramación. **Cita de última hora:** reserva que aprovecha un hueco.
