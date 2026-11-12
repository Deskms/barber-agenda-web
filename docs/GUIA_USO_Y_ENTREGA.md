# Barber Agenda: guía de uso y entrega a la app Android

Estado al 12 de noviembre de 2026. Rama `feature/agenda-whatsapp-mvp`.

## 1. Qué hay funcionando

- Backend Laravel 13 (PHP 8.4.27) con panel web, bot de WhatsApp y API móvil.
- API móvil bajo `/api/movil`: acceso por código, catálogo, disponibilidad, citas y perfil.
- 47 pruebas automáticas en verde (11 son de la API móvil).
- Base de datos real (MariaDB) con las tablas de acceso móvil y tres clientas de prueba.

No existe todavía: sellos o puntos de lealtad, pagos, ni conexión con el número real de WhatsApp de la barbería.

## 2. Reglas que no se rompen

- No se conecta ni se migra el número real de WhatsApp Business.
- No se envían mensajes a clientes reales.
- No se sube ningún token ni secreto a GitHub. Todo va en `.env`, que está ignorado.
- La IA no escribe en la base de datos. Toda escritura de la agenda pasa por `CitaService`.

## 3. Cómo levantar el sistema (cada vez que lo uses)

Necesitas la laptop encendida y tres cosas corriendo. Abre una terminal por cada una.

1. Base de datos: enciende MariaDB/MySQL en XAMPP.
2. Servidor web (déjala abierta):

   ```
   cd C:\dev\barber-agenda-web
   C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8010
   ```

3. Túnel de Cloudflare: inícialo como lo hayas dejado (apunta `citas.tunegociomx.app` a `http://localhost:8010`).

Solo si pruebas el bot de WhatsApp o los recordatorios, agrega dos terminales más:

```
C:\php84\php.exe artisan queue:work
C:\php84\php.exe artisan schedule:work
```

Siempre usa `C:\php84\php.exe`. El PHP de XAMPP es 8.2 y no sirve para este proyecto.

Si Cloudflare responde `502`, el servidor del paso 2 no está corriendo.

## 4. Comprobar que todo responde

Con el sistema levantado, pide un código de prueba:

```
curl.exe -X POST https://citas.tunegociomx.app/api/movil/auth/codigo -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"telefono\":\"5550000001\"}"
```

Debe responder `202` con `codigo_de_prueba`. Canjéalo antes de 10 minutos:

```
curl.exe -X POST https://citas.tunegociomx.app/api/movil/auth/token -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"telefono\":\"5550000001\",\"codigo\":\"CODIGO\"}"
```

Debe responder `200` con un `token`.

## 5. El interruptor que debes recordar: `APP_ENV`

- `APP_ENV=local`: la API devuelve `codigo_de_prueba` en la respuesta. Úsalo mientras tu amigo prueba.
- `APP_ENV=production`: el código solo se manda por WhatsApp y no aparece en la respuesta. Úsalo para la demo real.

Después de cambiarlo en `.env`, corre siempre:

```
C:\php84\php.exe artisan config:clear
```

Mientras esté en `local`, cualquiera que conozca la URL pública puede pedir un código y recibirlo en la respuesta. Regresa a `production` cuando tu amigo termine.

## 6. Comandos que ya corriste y cuándo repetirlos

| Comando | Cuándo |
|---|---|
| `artisan route:clear` | Si agregas rutas y salen 404. La caché de rutas queda vieja. |
| `artisan migrate` | Cuando haya migraciones nuevas. Hoy ya está al día. |
| `artisan db:seed --class=PruebasMovilSeeder` | Para recrear las clientas de prueba. Es seguro repetirlo. |
| `artisan test` | Antes de cada commit. Deben pasar las 47. |
| `artisan whatsapp:plantillas --crear` | Para mandar la plantilla `codigo_acceso` a Meta. Necesita aprobación. |

Teléfonos de prueba: `5550000001`, `5550000002`, `5550000003`. Son ficticios. No uses números de clientas reales.

## 7. Lo que debe leer tu amigo (en este orden)

1. `docs/API_MOVIL.md`: el contrato completo, con el JSON exacto de cada petición, la respuesta y los errores. Es lo principal.
2. `docs/openapi.yaml`: la misma API en formato OpenAPI. Sirve para generar el cliente de Retrofit o para pegárselo a su asistente.

Ambos están en la rama `feature/agenda-whatsapp-mvp` de `github.com/deskms/barber-agenda-web`.

### Mensaje para enviarle ahora

> Ya está arriba la API para la app. Lee `docs/API_MOVIL.md` completo y luego `docs/openapi.yaml`, en la rama `feature/agenda-whatsapp-mvp` del repo barber-agenda-web.
>
> URL base: `https://citas.tunegociomx.app/api/movil`. Solo responde cuando mi laptop está encendida con el servidor y el túnel activos; avísame antes de probar.
>
> Teléfonos de prueba: 5550000001, 5550000002 y 5550000003. El código llega en la respuesta (`codigo_de_prueba`) mientras yo tenga el modo de pruebas activo. No uses números reales.
>
> Reglas: no inventes endpoints que no estén en el documento. Si tu asistente propone algo que no aparece ahí, pregúntame. No hay sellos ni puntos todavía. Guarda el token en almacenamiento seguro del teléfono y no lo subas a GitHub. Tu tarea en Jira es SCRUM-58 (pantallas de acceso por código).

### Mañana, para darle su propio `.md`

Cuando hables con él, decide con él estas tres cosas y escríbelas en el archivo:

- Qué pantallas entran primero: acceso por código, catálogo, agendar, mis citas.
- Cómo guardará el token y la URL base, sin escribirlos en las pantallas.
- Cómo se avisan los cambios del contrato: si algo cambia en la API, se actualiza `API_MOVIL.md` antes de tocar la app.

## 8. Pendientes del equipo

- Decidir cuál rama es la evidencia del expediente: `feature/agenda-whatsapp-mvp` o `feature/backend-web-dominic`.
- Pasar la línea final de `artisan test` para fijar las aserciones en el apartado 1.4.
- Agregar la captura de la terminal con las 47 pruebas y una captura nueva del tablero de Jira.
- Borrar a mano SCRUM-4 en Jira.
- Completar en el expediente la dirección del repositorio móvil, las reflexiones individuales y la declaración de IA.
- Esperar la aprobación de Meta para la plantilla `codigo_acceso` antes de depender del envío real del código.
