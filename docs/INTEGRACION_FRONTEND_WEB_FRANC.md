# Integración del frontend web — para Franc

Este documento explica cómo consumir el backend web de Barber Agenda sin conocer cómo funciona por dentro. Todos los ejemplos son **respuestas reales** capturadas del backend corriendo con el catálogo del seeder; solo se recortaron listas largas y se ocultaron los tokens.

## 1. URL base y conexión

| Entorno | URL base |
|---|---|
| Local | `http://localhost:8000/api/web` (con `php artisan serve`) |
| Staging | Pendiente (SCRUM-44) |

Encabezados en **todas** las peticiones:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>   ← solo en rutas protegidas
```

**CORS:** por defecto se aceptan `http://localhost:5173` (Vite) y `http://localhost:3000`. Si tu servidor de desarrollo usa otro puerto, agrégalo en el `.env` del backend:

```
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:4200
```

No se usan cookies, así que **no** necesitas `withCredentials` ni el CSRF de Sanctum.

**Fechas:** todas salen en ISO 8601 con el desfase de Guadalajara, por ejemplo `2026-11-10T10:00:00-06:00`. Para enviar una fecha y hora puedes usar `2026-11-10 10:00` (se interpreta en hora de Guadalajara) o ISO 8601 con desfase.

**Envoltura:** los recursos individuales y las listas vienen dentro de `data`. Las listas paginadas (citas, clientas, usuarios) traen además `links` y `meta` con `current_page`, `last_page` y `total`.

## 2. Roles y permisos

| Rol | Quién es | Qué puede hacer |
|---|---|---|
| `admin` | La dueña | Todo: agenda completa, servicios, horarios, bloqueos, clientas, usuarios y difusiones (`/admin/*`). Puede agendar a nombre de cualquier clienta. |
| `cliente` | Clienta con cuenta | Ver servicios y disponibilidad, agendar para sí misma, ver, reprogramar y cancelar **sus** citas, y ver su historial. |

Usa `usuario.rol` de la respuesta de login para decidir qué menú mostrar. El backend valida los permisos de todos modos: una clienta que llame a `/admin/*` recibe 403.

## 3. Login y manejo del token

### Registro de clienta — `POST /auth/registro`

```json
{
  "nombre": "Ana López",
  "email": "ana@example.com",
  "telefono": "33 1234 5678",
  "password": "secreto123",
  "password_confirmation": "secreto123",
  "acepta_avisos": true
}
```

Respuesta **201**:

```json
{
  "token": "2|kXz…(token de ejemplo)",
  "tipo": "Bearer",
  "usuario": {
    "id": 2, "nombre": "Ana López", "email": "ana@example.com", "rol": "cliente", "activo": true,
    "cliente": { "id": 4, "nombre": "Ana López", "telefono": "5213312345678", "acepta_avisos": true, "tiene_cuenta": true, "creado_en": "2026-11-03T19:29:28-06:00" }
  }
}
```

- El teléfono se acepta con espacios, guiones o `+52`, y se guarda normalizado (`5213312345678`).
- Si la clienta ya había escrito por WhatsApp, su registro se **vincula** a esa clienta en lugar de duplicarla.
- `acepta_avisos` es el consentimiento para recibir avisos de horarios liberados. Muéstralo como una casilla **sin marcar** por defecto.

### Login — `POST /auth/login`

```json
{ "email": "ana@example.com", "password": "secreto123" }
```

Respuesta **200**: misma forma que el registro (`token`, `tipo`, `usuario`).

| Caso | Respuesta |
|---|---|
| Credenciales incorrectas | 422 `{"message":"Las credenciales no son correctas.","errors":{"email":["Las credenciales no son correctas."]}}` |
| Cuenta desactivada | 403 `{"message":"La cuenta está desactivada."}` |
| Demasiados intentos (más de 6 por minuto) | 429 |

### Usar el token

Guarda el token y envíalo en cada petición protegida: `Authorization: Bearer 2|kXz…`.

- `GET /auth/me` devuelve `{"data": {usuario}}`. Úsalo al recargar la página para saber si la sesión sigue viva.
- `POST /auth/logout` revoca el token actual; después bórralo del almacenamiento local.
- Si cualquier petición responde **401**, la sesión expiró o fue revocada: manda al usuario al login.

## 4. Manejo de errores

| Código | Cuándo | Forma del cuerpo |
|---|---|---|
| 401 | Falta el token o no es válido | `{"message":"No autenticado."}` |
| 403 | Rol sin permiso, cita ajena o cuenta desactivada | `{"message":"No tienes permiso para esta acción."}` |
| 404 | El recurso no existe | `{"message":"Recurso no encontrado."}` |
| 409 | El horario ya no está disponible | `{"message", "motivo", "alternativas": [...]}` |
| 422 | Datos inválidos o la cita no admite el cambio | `{"message", "errors": {"campo": ["mensaje"]}}`; en reglas de negocio solo viene `message` |
| 429 | Demasiados intentos de login o registro | — |
| 503 | WhatsApp no está configurado en el servidor | `{"message":"WhatsApp no está configurado en este servidor."}` |

Ejemplo real de 422 (registro con varios errores):

```json
{
  "message": "El valor de email ya está registrado. (y 3 errores más)",
  "errors": {
    "email": ["El valor de email ya está registrado."],
    "telefono": ["El teléfono no es válido. Usa 10 dígitos o el formato internacional."],
    "password": ["El campo contraseña debe tener al menos 8 caracteres.", "La confirmación de contraseña no coincide."]
  }
}
```

Muestra cada mensaje de `errors` debajo de su campo del formulario.

### Valores de `motivo` en un 409

| `motivo` | Texto sugerido para la pantalla |
|---|---|
| `en_el_pasado` | Ese horario ya pasó. |
| `dia_sin_atencion` | Ese día no hay atención. |
| `fuera_de_horario` | El servicio no termina antes del cierre. |
| `bloqueado` | La dueña bloqueó ese horario. |
| `estilista_ocupada` | Ese horario se acaba de ocupar. |
| `capacidad_llena` | Ya se atiende al máximo de clientas a esa hora. |

## 5. Flujo de reservación

1. `GET /servicios` → la clienta elige un servicio.
2. `GET /disponibilidad?servicio_id=5&fecha=2026-11-10` → se muestran los horarios libres. **No calcules horarios en el frontend.**
3. `POST /citas` con el horario elegido.
4. Si responde **201**, se muestra la confirmación. Si responde **409**, se informa que el horario se ocupó y se ofrecen las `alternativas` recibidas.
5. Deshabilita el botón de confirmar mientras se envía la petición, para evitar dobles reservas.

### `GET /servicios` (público)

```json
{
  "data": [
    { "id": 5, "nombre": "Corte de hombre", "duracion_minutos": 60, "precio": 150, "activo": true, "etapas_configuradas": false,
      "etapas": [{ "orden": 1, "nombre": "Corte de hombre", "duracion_minutos": 60, "ocupa_estilista": true }] },
    { "id": 6, "nombre": "Tinte", "duracion_minutos": 180, "precio": 900, "activo": true, "etapas_configuradas": true,
      "etapas": [
        { "orden": 1, "nombre": "Aplicación del tinte", "duracion_minutos": 30, "ocupa_estilista": true },
        { "orden": 2, "nombre": "Procesamiento", "duracion_minutos": 120, "ocupa_estilista": false },
        { "orden": 3, "nombre": "Enjuague y secado", "duracion_minutos": 30, "ocupa_estilista": true } ] }
  ]
}
```

`GET /servicios/{id}` devuelve un solo servicio, o 404 si está inactivo.

### `GET /disponibilidad?servicio_id=5&fecha=2026-11-10` (público)

`fecha` debe tener el formato `AAAA-MM-DD`.

```json
{
  "servicio_id": 5,
  "fecha": "2026-11-10",
  "horario": { "dia_semana": 2, "dia": "martes", "abre": "10:00", "cierra": "19:00", "activo": true },
  "slots": [
    { "inicio": "2026-11-10T10:00:00-06:00", "fin": "2026-11-10T11:00:00-06:00" },
    { "inicio": "2026-11-10T10:15:00-06:00", "fin": "2026-11-10T11:15:00-06:00" }
  ]
}
```

Si ese día no hay atención, `horario` es `null` y `slots` viene vacío: deshabilita el día en el calendario.

### `POST /citas`

Como clienta:

```json
{ "servicio_id": 6, "inicio": "2026-11-10 10:00", "notas": "Tono castaño" }
```

Como dueña, agrega `"cliente_id": 4`; es obligatorio para la dueña y está prohibido para una clienta.

Respuesta **201**:

```json
{
  "data": {
    "id": 1, "estado": "confirmada", "origen": "web",
    "inicio": "2026-11-10T10:00:00-06:00", "fin": "2026-11-10T13:00:00-06:00", "notas": "Tono castaño",
    "servicio": { "id": 6, "nombre": "Tinte", "precio": 900 },
    "cliente": { "id": 4, "nombre": "Ana López", "telefono": "5213312345678" },
    "bloques": [
      { "orden": 1, "nombre": "Aplicación del tinte", "inicio": "2026-11-10T10:00:00-06:00", "fin": "2026-11-10T10:30:00-06:00", "ocupa_estilista": true },
      { "orden": 2, "nombre": "Procesamiento", "inicio": "2026-11-10T10:30:00-06:00", "fin": "2026-11-10T12:30:00-06:00", "ocupa_estilista": false },
      { "orden": 3, "nombre": "Enjuague y secado", "inicio": "2026-11-10T12:30:00-06:00", "fin": "2026-11-10T13:00:00-06:00", "ocupa_estilista": true }
    ],
    "cancelada_en": null, "reprogramada_en": null, "atendida_en": null,
    "creada_en": "2026-11-03T19:29:28-06:00", "actualizada_en": "2026-11-03T19:29:28-06:00"
  }
}
```

Respuesta **409** (se intentó un corte a las 10:15, durante la aplicación del tinte):

```json
{
  "message": "La estilista está ocupada en ese horario.",
  "motivo": "estilista_ocupada",
  "alternativas": [
    { "inicio": "2026-11-10T10:30:00-06:00", "fin": "2026-11-10T11:30:00-06:00" },
    { "inicio": "2026-11-10T10:45:00-06:00", "fin": "2026-11-10T11:45:00-06:00" },
    { "inicio": "2026-11-10T11:00:00-06:00", "fin": "2026-11-10T12:00:00-06:00" }
  ]
}
```

### Estados y origen de una cita

| `estado` | Significado | Color sugerido |
|---|---|---|
| `confirmada` | Agendada y vigente | Ocupado |
| `cancelada` | Cancelada; su horario quedó libre | Tachado o gris |
| `completada` | La clienta fue atendida: cuenta como visita | Verde |
| `no_asistio` | La clienta no llegó | Rojo |

`origen` puede ser `web` (la agendó la clienta), `panel` (la dueña) o `whatsapp`.

### Otras operaciones de citas

| Petición | Cuerpo | Respuesta |
|---|---|---|
| `GET /citas` | Filtros opcionales: `desde`, `hasta`, `estado` y, solo la dueña, `cliente_id` | Lista paginada (50 por página). Una clienta solo recibe las suyas. |
| `GET /citas/{id}` | — | 200, o 403 si es ajena |
| `PATCH /citas/{id}` | `{"inicio":"2026-11-10 14:00"}` | 200 con la cita movida, 409 con alternativas o 422 si ya pasó |
| `POST /citas/{id}/cancelar` | — | 200 con `estado: "cancelada"`. Una clienta solo cancela citas que no han empezado (422 en otro caso). |
| `PATCH /admin/citas/{id}/estado` | `{"estado":"completada"}` o `"no_asistio"` | 200. Solo citas confirmadas que ya empezaron. |

## 6. Panel de la dueña

### Agenda — `GET /admin/agenda?fecha=2026-11-10&vista=dia` (o `vista=semana`)

La vista semanal va de lunes a domingo de la semana de `fecha`.

```json
{
  "vista": "dia",
  "desde": "2026-11-10T00:00:00-06:00",
  "hasta": "2026-11-11T00:00:00-06:00",
  "horarios": [{ "dia_semana": 2, "dia": "martes", "abre": "10:00", "cierra": "19:00", "activo": true }],
  "citas": [ { "id": 1, "estado": "cancelada", "…": "misma forma que una cita, con bloques" } ],
  "bloqueos": [ { "id": 1, "inicio": "2026-11-10T16:00:00-06:00", "fin": "2026-11-10T17:00:00-06:00", "motivo": "Comida" } ],
  "huecos": [ { "id": 2, "inicio": "…", "fin": "…", "motivo": "cancelacion", "servicio": { "id": 6, "nombre": "Tinte" } } ]
}
```

En el calendario pinta las etapas activas con color sólido y las de procesamiento con un tono claro; así la dueña ve por qué cabe un corte a mitad de un tinte. Incluye también las citas canceladas, para que se distingan de un vistazo.

### Actualización en vivo — `GET /admin/agenda/cambios?desde=<servidor_ahora anterior>`

```json
{ "servidor_ahora": "2026-11-03T19:29:29-06:00", "citas": [] }
```

Consulta cada 30 segundos. En cada llamada manda el `servidor_ahora` de la respuesta anterior; recibirás las citas creadas o modificadas desde entonces, por cualquier canal.

### Servicios

| Petición | Cuerpo |
|---|---|
| `GET /admin/servicios` | Incluye los inactivos |
| `POST /admin/servicios` | `{"nombre":"Manicura","duracion_minutos":45,"precio":250}`, o con etapas, como en el siguiente ejemplo |
| `PUT /admin/servicios/{id}` | Cualquier campo. Si mandas `etapas`, reemplazan a las anteriores y la duración se recalcula. |
| `DELETE /admin/servicios/{id}` | Lo **desactiva**; no lo borra |

```json
{
  "nombre": "Tinte", "precio": 900,
  "etapas": [
    { "nombre": "Aplicación", "duracion_minutos": 30, "ocupa_estilista": true },
    { "nombre": "Procesamiento", "duracion_minutos": 120, "ocupa_estilista": false },
    { "nombre": "Enjuague", "duracion_minutos": 30, "ocupa_estilista": true }
  ]
}
```

Reglas: al menos una etapa debe ocupar a la estilista y la duración total no puede pasar de 12 horas. Editar un servicio no cambia las citas que ya estaban agendadas.

### Horarios y bloqueos

| Petición | Cuerpo |
|---|---|
| `GET /horarios` (público) | Siempre devuelve 7 días; un día sin registro aparece con `activo: false` |
| `PUT /admin/horarios/{dia}` | `{"activo":true,"abre":"10:00","cierra":"19:00"}`; `dia` va de 0 (domingo) a 6 (sábado). Para cerrar: `{"activo":false}`. |
| `GET /admin/bloqueos` | Filtros `desde` y `hasta` opcionales; por defecto, desde hoy |
| `POST /admin/bloqueos` | `{"inicio":"2026-11-10 16:00","fin":"2026-11-10 17:00","motivo":"Comida"}` |
| `DELETE /admin/bloqueos/{id}` | — |

`POST /admin/bloqueos` responde **201** con `data` y `citas_afectadas`: las citas confirmadas que chocan con el bloqueo. El bloqueo **no** las cancela; muéstralas para que la dueña decida.

### Clientas

| Petición | Cuerpo y uso |
|---|---|
| `GET /admin/clientes?buscar=Ana` | Busca por nombre o teléfono; paginado |
| `POST /admin/clientes` | `{"nombre":"Lucía","telefono":"3311112222","acepta_avisos":false}` |
| `GET` / `PATCH /admin/clientes/{id}` | Editar nombre, teléfono o consentimiento |
| `GET /admin/clientes/{id}/historial` | `resumen` (`visitas`, `no_asistio`, `canceladas`, `ultima_visita`) y `citas` |
| `GET /admin/clientes/{id}/mensajes` | `mensajes` con `direccion` (`entrante` o `saliente`), `texto` y `fecha`; solo lectura |

La clienta ve su propio historial en `GET /mi/historial`, con la misma forma.

### Huecos y difusión

1. `GET /admin/huecos` → huecos futuros con `sigue_libre` (si el servicio original todavía cabe) y `difusiones_enviadas`.
2. `GET /admin/huecos/{id}/candidatas` → clientas elegibles: con consentimiento, que no liberaron el hueco, sin cita traslapada y que aún no lo recibieron.
3. La dueña marca a quién avisar y ve cuántos envíos se harán (el número de seleccionadas).
4. `POST /admin/huecos/{id}/difundir` con `{"cliente_ids":[4,7]}`.

```json
{ "hueco_id": 2, "enviadas": [4], "fallidas": [], "omitidas": [{ "cliente_id": 7, "razon": "No es elegible: …" }] }
```

| Respuesta | Significado |
|---|---|
| 409 | El hueco ya se ocupó |
| 422 | El hueco ya pasó |
| 503 | WhatsApp no está configurado en ese servidor |

La difusión **nunca** se dispara sola: siempre requiere esta confirmación de la dueña. El costo por mensaje que pide SCRUM-35 todavía no lo da el backend (falta la tarifa).

### Usuarios

| Petición | Cuerpo |
|---|---|
| `GET /admin/usuarios?rol=admin` | Lista paginada |
| `POST /admin/usuarios` | `{"nombre","email","password","rol"}`; si `rol` es `cliente`, también `telefono` |
| `PATCH /admin/usuarios/{id}` | `{"activo":false}` desactiva la cuenta y cierra sus sesiones. La dueña no puede desactivarse a sí misma. |

## 7. Datos que necesita cada pantalla

| Pantalla | Endpoints |
|---|---|
| Inicio de sesión y registro | `POST /auth/login`, `POST /auth/registro` |
| Catálogo de servicios | `GET /servicios` |
| Reservar (clienta) | `GET /disponibilidad`, `POST /citas` |
| Mis citas (clienta) | `GET /citas`, `POST /citas/{id}/cancelar`, `PATCH /citas/{id}` |
| Mi historial (clienta) | `GET /mi/historial` |
| Agenda del día y de la semana (dueña) | `GET /admin/agenda`, `GET /admin/agenda/cambios` cada 30 s |
| Crear cita desde el panel | `GET /admin/clientes?buscar=`, `GET /disponibilidad`, `POST /citas` con `cliente_id` |
| Detalle de cita (dueña) | `GET /citas/{id}`, `PATCH /citas/{id}`, `POST /citas/{id}/cancelar`, `PATCH /admin/citas/{id}/estado` |
| Servicios | `/admin/servicios` |
| Horarios y bloqueos | `GET /horarios`, `PUT /admin/horarios/{dia}`, `/admin/bloqueos` |
| Clientas | `/admin/clientes`, `/historial`, `/mensajes` |
| Difusión de huecos | `/admin/huecos`, `/candidatas`, `/difundir` |
| Usuarios | `/admin/usuarios` |

## 8. Para empezar

1. Levanta el backend siguiendo `BACKEND_WEB_DOMINIC.md`, sección 8.
2. Crea la cuenta de la dueña: `php artisan usuarios:crear-admin duena@ejemplo.com`.
3. Prueba el login desde tu frontend. Si el navegador bloquea la petición por CORS, revisa `CORS_ALLOWED_ORIGINS`.
4. Ante cualquier respuesta distinta a lo que dice este documento, avísame con la petición y la respuesta completas.
