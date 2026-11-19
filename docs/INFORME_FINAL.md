# Barber Agenda: informe de finalización

Fecha: 13 de noviembre de 2026. Repositorios: `barber-agenda-web` (rama
`feature/agenda-whatsapp-mvp`) y `barber-agenda-mobile` (rama `main`).

> **Estado honesto.** Todo lo de este informe está escrito y revisado, pero las
> pruebas de la última fase todavía **no se han ejecutado**. Desde el entorno
> de desarrollo asistido no fue posible correr Laravel (exige PHP 8.4) ni
> compilar Android (Maven y Gradle bloqueados). Al PHP solo se le revisó la
> sintaxis (`php -l`) y al Kotlin que no tuviera errores de sintaxis. Las
> columnas "Resultado" se llenan después de correr los pasos de la sección 6.

## 1. Qué se agregó o modificó

### Backend (Laravel)

| Área | Cambio |
|---|---|
| Apartado temporal | Quien responde "Lo quiero" a un aviso de hueco aparta el lugar **10 min**. Mientras tanto nadie más puede agendar ese horario por ningún canal. El apartado vence solo |
| Recuperación medible | `citas.difusion_id`: una cita en un hueco que se le avisó a esa clienta queda marcada como **recuperada** y el aviso pasa a `aceptada` |
| Vencimiento de ofertas | Comando `huecos:vencer` cada 15 min: los avisos cuyo horario ya pasó pasan a `vencida` |
| Ofertas en la app | `GET /api/movil/ofertas` y `POST /api/movil/ofertas/{id}/aceptar`. Aceptar intenta agendar y el servidor decide; solo una gana |
| Reprogramar desde la app | `POST /api/movil/citas/{id}/reprogramar`; la disponibilidad acepta `cita_id` para no tapar la cita propia |
| Inasistencias | En Promociones se fija un límite de faltas en 90 días. Al alcanzarlo, la clienta ya no agenda sola por la app ni por WhatsApp; la dueña sí puede agendarle desde el panel |
| Tarjeta de sellos | Un sello por cita completada, sin forma de sumarlos a mano, premio configurable, canje en el panel, tarjeta pública por QR y aviso por WhatsApp una vez por meta |
| Registro de llegada | QR del mostrador (se imprime y se puede cambiar desde Promociones). La agenda muestra "Llegó HH:MM" |
| Datos del local | `GET /api/movil/negocio` con `NEGOCIO_LAT` y `NEGOCIO_LNG` para la brújula |

### Correcciones

| Defecto | Corrección |
|---|---|
| Al reprogramar no se revisaba si la clienta ya tenía otra cita a esa hora en otra sección | Ahora se revisa, igual que al agendar |
| El gasto de mensajes del mes contaba solo avisos en estado `enviada`; al aceptarse o vencer dejaban de contarse | Cuenta `enviada`, `aceptada` y `vencida` |
| Caché de rutas vieja (las rutas nuevas daban 404) | Se documentó `artisan route:clear` |
| `Cliente` no era autenticable para Sanctum | Ahora extiende `Authenticatable` |

### Panel web

- **Tablero** nuevo (menú → Tablero), calculado solo con datos de la base:
  - Hoy: citas, llegadas registradas y atendidas.
  - Mes: atendidas, por atender, canceladas e inasistencias.
  - Ingresos: **obtenidos** (citas atendidas), **potenciales** (confirmadas sin atender) y **perdidos** (canceladas y faltas).
  - Recuperación: avisos enviados, espacios recuperados, monto **recuperado confirmado** y **recuperado obtenido**, y costo estimado de mensajes.
  - Ocupación por sección, servicios más solicitados y clientas con más faltas.
- La ficha de cada clienta muestra sus faltas en 90 días, sus sellos y el botón para entregar el premio.

### App Android

Pantallas que consumen la API, con carga, errores y manejo de desconexión:

- Acceso por código de WhatsApp.
- Agendar: servicio, día y hora, más las **ofertas de huecos** con el botón "Lo quiero".
- Mis citas (próximas e historial): **cambiar horario**, cancelar, "Ya llegué" con QR y "Cómo llegar".
- Tarjeta de sellos.
- Perfil: nombre, consentimiento de avisos, huella y **diagnóstico de sensores**.
- Sensores: acelerómetro y magnetómetro, ver `docs/SENSORES.md` del repositorio móvil.

## 2. Reglas de negocio: dónde vive cada una

Toda escritura de la agenda pasa por `App\Services\Agenda\CitaService`. Los
tres canales (panel, WhatsApp y app) lo usan, así que comparten las mismas
reglas:

1. El servidor es la única autoridad: revalida dentro de una transacción.
2. Se bloquea el día de la semana (`HorarioAtencion::lockForUpdate`) para serializar reservas simultáneas.
3. Choques solo dentro de la misma sección.
4. Una clienta no puede estar en dos citas a la vez.
5. Se respeta el apartado de otra clienta.
6. Se aplica el límite de faltas, excepto desde el panel.
7. La IA del bot solo interpreta el texto y propone; nunca escribe en la base.

## 3. Pruebas automatizadas del backend

| Archivo | Pruebas | Qué cubre | Resultado |
|---|---|---|---|
| AgendaTest | 10 | Traslapes, secciones, horario, cancelar, reprogramar | Pasaron el 12-nov |
| ApiMovilTest | 11 | Acceso por código, citas propias, 401/404/409 | Pasaron el 12-nov |
| CampanasTest | 7 | Avisos de huecos, presupuesto, "Lo quiero", "ya te toca volver" | Pasaron el 12-nov; **volver a correr** (cambió `tomarHueco`) |
| PanelTest | 6 | Sesión, vistas, crear cita, difusión | Pasaron el 12-nov; volver a correr |
| WhatsAppBotTest | 13 | Webhook firmado, flujo con botones e IA | Pasaron el 12-nov; volver a correr |
| SellosTest | 6 | Sello único, revertir, aviso una vez, canje, tarjeta pública, API | Pendiente |
| LlegadaTest | 5 | QR válido, ajeno, sin cita, código cambiado, datos del local | Pendiente |
| HuecosTest | 9 | Apartado y su vencimiento, recuperación, ofertas simultáneas, vencer avisos, faltas, reprogramar, tablero | Pendiente |

Total esperado: **67 pruebas**.

## 4. Lo que no se puede probar con pruebas automáticas (se prueba a mano)

| Prueba | Cómo | Resultado |
|---|---|---|
| Compilación Android | Android Studio → Build | Pendiente |
| Flujo completo app ↔ web | Agendar en la app y verlo en el panel; cancelar en el panel y verlo en la app | Pendiente |
| WhatsApp real (número de prueba) | Mensaje desde un número autorizado → respuesta del bot | Pendiente |
| Sensores | Tabla S1 a S8 de `docs/SENSORES.md` en un teléfono físico | Pendiente |
| QR de llegada | Escanear el QR de Promociones con la app | Pendiente |
| Pérdida de conexión | Apagar `artisan serve` y usar la app | Debe mostrar "El servidor no está disponible" sin cerrarse |

## 5. Limitaciones externas (no son defectos del código)

- **Número de prueba de Meta:** solo entrega mensajes a hasta 5 números verificados. Los teléfonos `52155500000xx` del seeder no reciben mensajes; por eso existe `codigo_de_prueba` cuando `APP_ENV=local`.
- **Plantillas:** `codigo_acceso`, `tarjeta_completa` y las demás necesitan aprobación de Meta. Mientras no estén aprobadas, solo funcionan los mensajes dentro de la ventana de 24 h.
- **Costos:** en producción las plantillas de marketing cuestan. El tablero muestra un **estimado** con la tarifa configurada; el número de prueba no genera cargos.
- **Infraestructura:** el servidor de demostración es la laptop con un túnel de Cloudflare. Si la laptop se apaga, la app no tiene servidor. Conviene grabar un video de respaldo.
- **Montos:** las citas no guardan el precio cobrado, así que los ingresos se calculan con el precio actual del servicio. Por eso se presentan como estimados.
- **Apartado de huecos:** la lista de horarios libres no oculta un horario apartado; si alguien lo elige recibe "apartado unos minutos por otra persona" y elige otro.
- **IA:** el intérprete de texto libre depende de `ANTHROPIC_API_KEY`. Sin clave, el bot funciona solo con botones y palabras clave (probado en `WhatsAppBotTest`).
- **Google Wallet:** no se implementó. Requiere cuentas de Google Cloud y de la consola de Wallet, y la tarjeta ya existe en la app y en la web.
- **NFC:** no se implementó para no comprar etiquetas. La llegada se registra con la cámara.

## 6. Ejecutar desde cero

### Servidor (Windows)

```
cd C:\dev\barber-agenda-web
composer install          (con PHP 8.4 en el PATH: set PATH=C:\php84;%PATH%)
copy .env.example .env            (solo la primera vez; llenar los datos de MariaDB y Meta)
C:\php84\php.exe artisan key:generate   (solo la primera vez)
C:\php84\php.exe artisan migrate
C:\php84\php.exe artisan db:seed --class=PruebasMovilSeeder
C:\php84\php.exe artisan optimize:clear
C:\php84\php.exe artisan test
```

En `.env`: `APP_ENV=local` mientras se prueba la app. Agregar también
`NEGOCIO_LAT` y `NEGOCIO_LNG` con las coordenadas del local.

Para levantarlo, una terminal por cada proceso:

```
C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8010
C:\php84\php.exe artisan queue:work
C:\php84\php.exe artisan schedule:work
```

Además, el túnel de Cloudflare hacia `http://localhost:8010`.

### App

1. Android Studio → Open `C:\dev\barber-agenda-mobile` → Sync → Run.
2. APK: Build → Build APK(s) → `app/build/outputs/apk/debug/app-debug.apk`.
3. La URL del servidor está en `gradle.properties` (`BARBER_API_URL`).

### Demostración sugerida (10 minutos)

1. **App:** entrar con `5550000001` y agendar.
2. **Panel:** la cita aparece en la agenda.
3. **Recuperación:** cancelar la cita en el panel → aviso de hueco → otra clienta lo toma desde Agendar → ofertas en la app → el Tablero cuenta 1 espacio recuperado.
4. **Llegada:** "Ya llegué" escaneando el QR → en la agenda aparece "Llegó".
5. **Sellos:** marcar la cita como completada → sube el sello → agitar el teléfono abre la tarjeta.
6. **Brújula:** "Cómo llegar" con el magnetómetro.
7. **Evidencia de sensores:** Perfil → Sensores del teléfono, con las lecturas en vivo.
