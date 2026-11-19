# Barber Agenda: lista de pruebas y guion de presentación

Esta lista sirve para comprobar que **todo** el sistema funciona antes de presentarlo
y para planear la presentación. Marca cada casilla solo si la viste funcionar.
Si algo falla, anota en "Notas" qué pasó y el mensaje exacto.

Responsables: **Domi** (desarrollo, pruebas y demostración) y **Franc** (presentación y
diseño final).

---

## 0. Arranque (cada vez que vayas a probar)

Abre una terminal por proceso, en `C:\dev\barber-agenda-web`:

| # | Terminal | Comando |
|---|---|---|
| 1 | Servidor | `C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8010` |
| 2 | Cola (mensajes de WhatsApp) | `C:\php84\php.exe artisan queue:work -v` |
| 3 | Tareas programadas (vencer avisos) | `C:\php84\php.exe artisan schedule:work` |
| 4 | Túnel | Cloudflare hacia `http://localhost:8010` |

- [ ] Solo después de actualizar código: `C:\php84\php.exe artisan migrate` y luego `C:\php84\php.exe artisan optimize:clear`
- [ ] En `.env`: `APP_ENV=local`, `ANTHROPIC_API_KEY` vacío (sin costo) y el token de Meta vigente
- [ ] El token temporal de Meta dura unas 24 h. Si aparece el error `190` en `storage/logs/whatsapp-*.log`, genera otro
- [ ] `https://citas.tunegociomx.app` abre el panel (si sale 502, el servidor no está corriendo)
- [ ] Si `queue:work` parece congelado, presiona **Enter** en esa ventana (Windows pausa la terminal cuando das clic dentro)

## 1. Pruebas automáticas

- [ ] `C:\php84\php.exe artisan test` → **71 pruebas en verde**

| Archivo | Pruebas |
|---|---|
| AgendaTest | 10 |
| ApiMovilTest | 11 |
| CampanasTest | 7 |
| PanelTest | 6 |
| WhatsAppBotTest | 13 |
| SellosTest | 6 |
| LlegadaTest | 5 |
| HuecosTest | 11 |
| WhatsAppFormatoMexicoTest | 2 |

Si falla una, copia el nombre de la prueba y el mensaje completo.

## 2. Panel web (la dueña)

Abre el panel con **Ctrl + F5** para cargar el diseño nuevo.

| ✓ | Qué hacer | Qué debes ver |
|---|---|---|
| [ ] | Entrar con el correo de la dueña | Barra superior blanca translúcida, logo "BA" negro, "Agenda" en café |
| [ ] | Agenda → Día | Una columna por sección, horas a la izquierda, bloques por estado y línea roja de "ahora" |
| [ ] | Clic en una cita | Panel derecho con estado, datos, botones de WhatsApp y teléfono, y "Marcar atendida" |
| [ ] | Clic en "Libre hasta las…" | Abre "Nueva cita" con fecha y hora ya llenas |
| [ ] | Crear una cita encima de otra en la misma sección | Rechaza con mensaje claro; no se guarda |
| [ ] | Mover una cita | Cambia de lugar; el horario anterior queda libre |
| [ ] | Cancelar con "Avisarle por WhatsApp" | La cita desaparece de la agenda y llega la plantilla `cita_cancelada` al número verificado |
| [ ] | Marcar "Atendida" | El bloque se pone gris grafito y sube un sello en la tarjeta de la clienta |
| [ ] | Marcar "No asistió" | El bloque se pone color óxido y la ficha de la clienta suma una falta |
| [ ] | Vista Semana | 7 días, el de hoy marcado en azul ballena |
| [ ] | Tablero | Citas de hoy, ingresos obtenidos/potenciales/perdidos, espacios recuperados y costo **estimado** |
| [ ] | Clientas → ficha | Faltas en 90 días, sellos con QR de su tarjeta y botón para entregar el premio |
| [ ] | Promociones → sellos | Cambiar la meta y el premio; se refleja en la app |
| [ ] | Promociones → faltas | Poner el límite en 2; la clienta con 2 faltas ya no puede agendar sola |
| [ ] | Promociones → QR del mostrador | Se ve y se puede imprimir. "Cambiar código" invalida el QR anterior |
| [ ] | Espacios → hueco → enviar aviso | "Enviadas: 1" para tu número; las clientas ficticias salen como fallidas, y eso es normal |
| [ ] | Servicios y Horario | Editar precio o duración y cerrar un día; la disponibilidad cambia en la app |
| [ ] | En el celular, abrir el panel | Se adapta: el calendario se desplaza a los lados y el detalle baja |

## 3. WhatsApp (número de prueba, sin costo)

Solo reciben mensajes los números verificados en Meta (el tuyo y el de tu amigo).

| ✓ | Qué hacer | Qué debes ver |
|---|---|---|
| [ ] | Escribir "hola" | Menú con botones: Agendar, Mis citas, Horarios y precios |
| [ ] | Agendar → servicio → día → hora → Confirmar | Confirmación, y la cita aparece en el panel con origen **WhatsApp** |
| [ ] | Mis citas → Cancelar | Se cancela y el horario queda libre en el panel |
| [ ] | "cuánto cuesta" | Lista de precios (sin IA, por palabras clave) |
| [ ] | "BAJA" y luego "ALTA" | Deja de recibir avisos y los vuelve a activar |
| [ ] | Tu amigo escribe "hola" | **Sí** le llegan los botones (se corrigió el formato 52 / 521 de México) |
| [ ] | **Hueco:** la dueña te avisa desde Espacios → contestas "Agendar" (o "sí") | Te ofrece **ese** espacio y te pide confirmar (corrección de hoy) |
| [ ] | Confirmar el hueco | En el panel la cita sale como **Recuperada** y el Tablero suma 1 espacio recuperado |
| [ ] | No confirmar en 10 min | El apartado vence y otra persona ya puede tomar el lugar |
| [ ] | Revisar Meta → Facturación y pagos | Saldo **$0.00** y ningún método de pago |

## 4. App Android

Android Studio → **Sync** → **Run** en el teléfono físico.

| ✓ | Qué hacer | Qué debes ver |
|---|---|---|
| [ ] | Abrir la app | Logo "BA", título grande "Barber Agenda" y fondo gris claro |
| [ ] | Entrar con tu número de 10 dígitos | Código de 6 dígitos (en modo pruebas lo llena solo) |
| [ ] | Pestañas | Barra inferior blanca; la pestaña activa en azul ballena y las demás en gris |
| [ ] | Agendar: servicio → día → hora → Confirmar | Palomita azul en el servicio elegido; la cita aparece en el panel con origen **App** |
| [ ] | Elegir un horario que otra persona acaba de tomar | Mensaje "ya no está disponible" y la lista de horarios se actualiza |
| [ ] | Con un aviso de hueco reciente, abrir Agendar | Arriba aparece "Se liberó un espacio para ti" con el botón **Lo quiero** |
| [ ] | Mis citas → Cambiar horario | Lista de horarios, sin incluir el actual; al confirmar se mueve también en el panel |
| [ ] | Mis citas → Cancelar | Pregunta antes de cancelar y el estado cambia a Cancelada |
| [ ] | "Ya llegué" **sin** cita cercana | "Tu próxima cita es … Puedes registrar tu llegada desde 60 minutos antes." |
| [ ] | "Ya llegué" desde 60 min antes hasta 30 min después de la cita | "Llegada registrada", y en el panel la cita cambia a café "En el local" |
| [ ] | Cómo llegar | La flecha apunta al local y la distancia se parece a la de Maps (requiere `NEGOCIO_LAT/LNG`) |
| [ ] | Agitar el teléfono | Se abre la pestaña Tarjeta |
| [ ] | Tarjeta | Sellos llenos en café, faltantes en gris y aviso de premio si ya lo ganó |
| [ ] | Perfil → huella | Pide la huella; al volver a abrir la app la pide de nuevo |
| [ ] | Perfil → apagar avisos | Esa clienta deja de aparecer como candidata en Espacios |
| [ ] | Apagar `artisan serve` y usar la app | "El servidor no está disponible" y la app **no se cierra** |
| [ ] | Sensores S1 a S8 | Tabla de `docs/SENSORES.md` del repositorio móvil (versión de pruebas) |

## 5. Reglas que deben cumplirse siempre

- [ ] Dos personas no pueden quedarse el mismo horario en la misma sección, ni desde canales distintos.
- [ ] Una clienta no ve las citas de otra: entra con otro número y revisa "Mis citas".
- [ ] La IA no escribe en la base: sin clave el bot funciona igual con botones.
- [ ] Ningún token está en GitHub: `.env` está en `.gitignore`.
- [ ] Con el límite de faltas alcanzado, la clienta no agenda sola, pero la dueña sí desde el panel.

## 6. Limitaciones conocidas (dilas tú antes de que te las pregunten)

- El número de prueba de Meta solo entrega a 5 números verificados.
- Las plantillas necesitan aprobación de Meta; `espacio_liberado` estuvo pendiente.
- El servidor es la laptop con un túnel: si se apaga, la app se queda sin servidor.
- Los ingresos se calculan con el precio actual del servicio, así que son **estimados**.
- Google Wallet y NFC no se implementaron, a propósito, para no generar costos ni comprar hardware.

## 7. Después de que todo pase

- [ ] Commit en los dos repositorios. **Solo cuando decidas subir a git.**
- [ ] Mover las historias de Jira (SCRUM-53 a 79) a Finalizado, con capturas como evidencia.
- [ ] Llenar la columna "Resultado" de `docs/INFORME_FINAL.md` y de `docs/SENSORES.md`.
- [ ] Copiar `respaldos/*.tgz` fuera de las carpetas del proyecto (USB o nube).

---

# Presentación

## Idea central (una frase)

> "Una barbería pierde dinero por citas canceladas y por gente que no llega.
> Barber Agenda llena esos huecos sola por WhatsApp y te dice cuánto recuperaste."

El hilo de toda la presentación es el **hueco recuperado**: es lo más original del
sistema y se puede medir en el Tablero.

## Estructura sugerida (12 minutos)

| Min | Bloque | Quién | Contenido |
|---|---|---|---|
| 0–1 | Problema | Franc | Agenda en libreta, mensajes sueltos por WhatsApp, cancelaciones de última hora, gente que no llega |
| 1–2 | Solución | Franc | Tres canales (panel, WhatsApp y app) sobre **una sola agenda** que no permite citas encimadas |
| 2–3 | Arquitectura | Domi | Diagrama: Laravel + MariaDB al centro; WhatsApp Cloud API, app Android y panel alrededor. "La IA solo interpreta, nunca escribe en la base" |
| 3–9 | **Demo en vivo** | Domi | Guion de abajo |
| 9–10 | Resultados | Franc | Captura del Tablero, 71 pruebas en verde y la facturación de Meta en $0.00 |
| 10–11 | Limitaciones y siguiente paso | Domi | Sección 6, en tono honesto; número real de la barbería y hosting cuando haya clienta |
| 11–12 | Preguntas | Los dos | — |

## Guion de la demo (6 minutos)

Prepara todo antes de entrar (sección "Preparación").

1. **App, agendar (1 min).** Entrar con tu número, elegir "Corte de cabello", un día y una hora → Confirmar.
2. **Panel (30 s).** La cita ya aparece en la agenda con la etiqueta **App**. Mostrar los colores por estado.
3. **Hueco (2 min).** En el panel cancelar la cita de la clienta preparada → Espacios → avisar a tu número → en tu WhatsApp contestar "Agendar" → confirmar → la cita sale **Recuperada** → Tablero: "1 espacio recuperado, $180".
4. **Llegada (1 min).** "Ya llegué" → escanear el QR impreso → en el panel la cita cambia a café **En el local**.
5. **Sellos (1 min).** Marcar la cita como Atendida → agitar el teléfono → se abre la tarjeta con un sello más.
6. **Brújula (30 s).** "Cómo llegar": girar el teléfono y la flecha sigue al local.

## Preparación (el día anterior y 30 minutos antes)

- [ ] Generar un **token nuevo de Meta** ese mismo día (el temporal dura unas 24 h).
- [ ] Crear desde el panel una cita a la **hora de la presentación**: el QR de llegada solo funciona de 60 min antes a 30 min después.
- [ ] Tener una cita de otra clienta lista para cancelar en el paso 3, en un horario que tu servicio habitual sí alcance.
- [ ] QR del mostrador **impreso** (Promociones → QR).
- [ ] Teléfono con batería, brillo alto y datos móviles (por si falla el Wi-Fi del salón).
- [ ] Laptop conectada a la corriente, sin suspensión, con las 4 terminales corriendo.
- [ ] **Video de respaldo** grabado con el mismo guion, por si falla internet o Meta.
- [ ] Capturas de respaldo: Tablero, Facturación $0.00 y pruebas en verde.

## Preguntas probables y respuesta corta

| Pregunta | Respuesta |
|---|---|
| ¿Qué pasa si dos personas agendan la misma hora al mismo tiempo? | El servidor bloquea el día dentro de una transacción; solo una gana y la otra recibe "ya no está disponible". Hay prueba automática de eso. |
| ¿Cuánto cuesta operarlo? | En pruebas, $0. En producción, los mensajes de marketing de Meta cuestan unos US$0.04 cada uno y el panel tiene un tope de presupuesto. Los mensajes de respuesta dentro de 24 h son gratis. |
| ¿Por qué no usan la IA para todo? | La IA solo entiende texto libre y propone. Las reglas (horarios, choques, faltas) las decide el código, para que el sistema sea predecible. Sin clave de IA, funciona con botones. |
| ¿Qué sensores usa? | Acelerómetro (agitar para abrir la tarjeta) y magnetómetro (brújula). Como apoyo: cámara para el QR, GPS y huella. |
| ¿Es seguro? | Se entra con un código de WhatsApp, sin contraseñas. Hay tokens por dispositivo, cada clienta ve solo sus citas, el webhook va firmado y no hay secretos en el repositorio. |
| ¿Se puede vender? | Sí. El panel es independiente del negocio, y el plan siguiente es usarlo con varios negocios (multiinquilino). |

## Diseño (para Franc)

Paleta usada en el panel y en la app (estilo minimalista tipo iPhone):

| Uso | Color | Hex |
|---|---|---|
| Acción principal, confirmada | Azul ballena | `#1E3A5F` |
| Marca, "en el local", sellos | Café | `#6F4E37` |
| Logo, texto | Negro | `#111113` |
| Atendida | Grafito | `#3A3A3C` |
| Faltas, cancelar | Óxido | `#A4462F` |
| Fondo | Gris iOS | `#F2F2F7` |
| Tarjetas | Blanco | `#FFFFFF` |

Usa la misma paleta en las diapositivas para que todo se vea como un solo producto.
