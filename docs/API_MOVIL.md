# API móvil — Barber Agenda

Contrato para la aplicación Android. La app **no tiene base de datos propia**:
consume esta API, que escribe en la misma base y por los mismos servicios de
dominio que el panel web. Agendar y cancelar pasan por `CitaService`, con su
transacción y su bloqueo, así que no hay forma de que la app y el panel se
peleen por el mismo horario.

| Dato | Valor |
|---|---|
| Base URL de pruebas | `https://citas.tunegociomx.app` |
| Prefijo | `/api/movil` |
| Formato | JSON en ambos sentidos |
| Encabezados | `Accept: application/json`, `Content-Type: application/json` |
| Autenticación | `Authorization: Bearer <token>` (Laravel Sanctum) |
| Zona horaria | Las fechas salen en ISO 8601 con offset de `America/Mexico_City` |

> El entorno de pruebas corre en una laptop detrás de un túnel de Cloudflare.
> Solo está disponible cuando esa máquina está encendida. Antes de integrar,
> confirma con el equipo web que está arriba.

---

## 1. Autenticación

Las clientas **no tienen contraseña**. Viven en la tabla `clientes`,
identificadas por su número de WhatsApp. Para entrar se manda un código de seis
dígitos a ese WhatsApp y se canjea por un token.

```
POST /api/movil/auth/codigo   →  llega el código al WhatsApp
POST /api/movil/auth/token    →  el código se cambia por un token
(todo lo demás)               →  Authorization: Bearer <token>
POST /api/movil/auth/salir    →  revoca ese token
```

El token vive **60 días** y se revoca de forma individual: si se pierde el
teléfono, se borra ese token sin tocar las demás sesiones.

### 1.1 Pedir el código

`POST /api/movil/auth/codigo` — sin autenticación. Límite: 5 peticiones cada 10 minutos.

```json
{ "telefono": "3331111111" }
```

El teléfono se normaliza al formato de Meta: 10 dígitos se completan a
`521XXXXXXXXXX`. También se acepta ya completo (`5213331111111`).

**202 Accepted**

```json
{
  "mensaje": "Si el número es válido, llegará un código por WhatsApp.",
  "vigencia_minutos": 10,
  "codigo_de_prueba": "482913"
}
```

`codigo_de_prueba` llega **solo fuera de producción**, para que puedas
desarrollar sin esperar a que Meta entregue el mensaje. En producción es `null`.

La respuesta es la misma exista o no la clienta: no se revela quién es cliente
del negocio. Si el número es nuevo, se da de alta con los avisos apagados.

### 1.2 Canjear el código

`POST /api/movil/auth/token` — sin autenticación. Límite: 10 cada 10 minutos.

```json
{ "telefono": "3331111111", "codigo": "482913", "dispositivo": "Pixel 7 de Ana" }
```

**200 OK**

```json
{
  "token": "7|Xv2kQ9fL0pR...",
  "expira_en": "2027-01-11T00:00:00-06:00",
  "cliente": {
    "id": 2,
    "telefono": "5213331111111",
    "nombre": "Ana",
    "acepta_avisos": true
  }
}
```

**422** si el código es incorrecto, ya se usó o venció:

```json
{ "mensaje": "Código inválido o vencido." }
```

Un código se invalida al usarse, a los 10 minutos, al pedir uno nuevo, y a los
5 intentos fallidos.

### 1.3 Cerrar sesión

`POST /api/movil/auth/salir` — con token. Devuelve `{"mensaje": "Sesión cerrada."}`.

---

## 2. Catálogo

`GET /api/movil/catalogo`

Las **secciones** son partes del local con agenda independiente: mientras en
color hacen un tinte de tres horas, en cortes atienden a otras personas. Por eso
el catálogo viene agrupado por sección y no como una lista plana.

**200 OK**

```json
{
  "data": [
    {
      "id": 2,
      "nombre": "Color y estética",
      "servicios": [
        { "id": 3, "nombre": "Maquillaje", "duracion_minutos": 120, "precio": 800.0, "seccion_id": 2 },
        { "id": 4, "nombre": "Manicura",   "duracion_minutos": 45,  "precio": 250.0, "seccion_id": 2 }
      ]
    },
    {
      "id": 1,
      "nombre": "Cortes de hombre",
      "servicios": [
        { "id": 1, "nombre": "Corte de cabello", "duracion_minutos": 60, "precio": 180.0, "seccion_id": 1 }
      ]
    }
  ]
}
```

`precio` puede ser `null` si el servicio no tiene precio fijo.

---

## 3. Disponibilidad

`GET /api/movil/disponibilidad?servicio_id=1&fecha=2026-11-14`

Devuelve los inicios donde el servicio **completo** cabe: ya descuenta el
horario de atención, los bloqueos y las citas de esa sección.

**200 OK**

```json
{
  "fecha": "2026-11-14",
  "servicio_id": 1,
  "data": [
    { "inicio": "2026-11-14T10:00:00-06:00", "fin": "2026-11-14T11:00:00-06:00", "hora": "10:00" },
    { "inicio": "2026-11-14T10:15:00-06:00", "fin": "2026-11-14T11:15:00-06:00", "hora": "10:15" }
  ]
}
```

Un día cerrado devuelve `"data": []`, no un error. Los horarios se traslapan
entre sí porque el paso es de 15 minutos: son inicios posibles, no bloques.

**No caches esta respuesta.** Entre que se muestra y la clienta elige pueden
pasar minutos y alguien más puede tomar el lugar; el servidor revalida al
agendar y por eso existe el 409.

---

## 4. Citas

### 4.1 Agendar

`POST /api/movil/citas`

```json
{ "servicio_id": 1, "inicio": "2026-11-14T10:00" }
```

`inicio` va en hora local, **sin zona ni segundos**: `Y-m-d\THH:mm`.

**201 Created**

```json
{
  "data": {
    "id": 41,
    "estado": "confirmada",
    "origen": "movil",
    "inicio": "2026-11-14T10:00:00-06:00",
    "fin": "2026-11-14T11:00:00-06:00",
    "puede_cancelarse": true,
    "servicio": { "id": 1, "nombre": "Corte de cabello", "duracion_minutos": 60, "precio": 180.0, "seccion_id": 1 },
    "seccion":  { "id": 1, "nombre": "Cortes de hombre" }
  }
}
```

**409 Conflict** — la petición está bien, el horario ya no:

```json
{ "mensaje": "Ese espacio acaba de ser ocupado." }
```

Otros mensajes con el mismo 409: `"Ese horario queda fuera del horario de atención."`,
`"Ese horario ya pasó."`, `"Ya tienes otra cita a esa hora."` (la misma clienta
no puede estar en dos sillas, aunque sean secciones distintas).

Ante un 409, vuelve a pedir la disponibilidad: no reintentes el mismo horario.

### 4.2 Mis citas

`GET /api/movil/citas` — opcionales: `?estado=confirmada` y `?desde=2026-11-01`.
Devuelve las 100 más recientes, de la más nueva a la más vieja.

Estados: `confirmada`, `cancelada`, `completada`, `no_asistio`.

**200 OK** — `{"data": [ ...objetos cita... ]}`, mismo objeto que en 4.1.

### 4.3 Cancelar

`POST /api/movil/citas/{id}/cancelar`

**200 OK** con la cita ya en `"estado": "cancelada"`.

**404** si la cita no es de quien pregunta. Es deliberado: un 403 confirmaría
que ese identificador existe. **409** si la cita ya pasó.

Cancelar es idempotente y libera el hueco, que el sistema puede ofrecer a otras
clientas por WhatsApp.

---

## 5. Perfil

`GET /api/movil/perfil` → `{"data": {"id": 2, "telefono": "...", "nombre": "Ana", "acepta_avisos": true}}`

`PUT /api/movil/perfil` con `{"nombre": "Ana López", "acepta_avisos": false}`.
Ambos campos son opcionales; se actualiza solo lo que mandes.

`acepta_avisos` es el consentimiento para recibir avisos de espacios liberados.
Apagarlo saca a la clienta de todas las difusiones. **Debe haber una forma
visible de apagarlo en la app**: no es una preferencia cualquiera, es el
consentimiento que exige la ley de datos personales y las reglas de Meta.

---

## 6. Errores

| Código | Cuándo | Cuerpo |
|---|---|---|
| 401 | Falta el token o ya no sirve | `{"message": "Unauthenticated."}` |
| 404 | El recurso no existe **o no es tuyo** | `{"message": "..."}` |
| 409 | El horario ya no está disponible | `{"mensaje": "..."}` |
| 422 | Datos inválidos | `{"message": "...", "errors": {"campo": ["..."]}}` |
| 429 | Demasiadas peticiones | `{"message": "Too Many Attempts."}` |

Nota: los errores de validación de Laravel usan `message` (inglés) y los
nuestros usan `mensaje`. Al leerlos, contempla las dos llaves.

Límites: 5 cada 10 min en `auth/codigo`, 10 cada 10 min en `auth/token`, 60 por
minuto en el resto. El 429 trae `Retry-After`.

---

## 7. Datos de prueba

```bash
C:\php84\php.exe artisan db:seed --class=PruebasMovilSeeder
```

Crea tres clientas ficticias —`5215550000001`, `5215550000002`,
`5215550000003`— con citas de ejemplo. Esos números no están verificados en
Meta, así que **no reciben mensajes**: usa el `codigo_de_prueba` que devuelve
`auth/codigo` para entrar.

Úsalas en lugar de números reales: el entorno apunta a la base de desarrollo y
una cita de prueba con un número real aparecería en la agenda de verdad.

### Ejemplo completo con curl

```bash
BASE=https://citas.tunegociomx.app/api/movil

CODIGO=$(curl -s -X POST $BASE/auth/codigo \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"telefono":"5215550000001"}' | jq -r .codigo_de_prueba)

TOKEN=$(curl -s -X POST $BASE/auth/token \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d "{\"telefono\":\"5215550000001\",\"codigo\":\"$CODIGO\"}" | jq -r .token)

curl -s $BASE/catalogo -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
curl -s "$BASE/disponibilidad?servicio_id=1&fecha=2026-11-14" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

---

## 8. Pendiente

**Puntos y recompensas (tarjeta de sellos) todavía no existe.** No hay tabla ni
endpoints. Está documentada como innovación INN-03 y en Jira como SCRUM-68 a 70.
Cuando se construya, se agregará `GET /api/movil/sellos` a este contrato. No
programes contra ella todavía.

El spec para generar cliente está en `docs/openapi.yaml`.
