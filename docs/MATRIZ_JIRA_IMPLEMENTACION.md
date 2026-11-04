# Matriz Jira ↔ implementación

Fuente: proyecto **SCRUM** en barberagenda.atlassian.net, leído el 3 de noviembre de 2026 (57 issues). **No se modificó ningún estado en Jira.**

## Cómo leer esta matriz

- **Responsable en Jira:** la persona asignada hoy en Jira. Dos integrantes aparecen con su matrícula (2122100571 y 2124200352) porque así están dados de alta; no adiviné a quién corresponde cada una.
- **Responsable según la división oficial:** Dominic = backend web, Franc = frontend web, José = backend móvil, Rodri = frontend móvil.
- **Estado real:** lo que verifiqué en el código de la rama `feature/backend-web-dominic`. "Implementado" significa que hay código **y** prueba que lo respalda.
  - *Implementado (backend):* la parte de servidor está lista y probada; falta la interfaz de otro integrante.
  - *Parcial:* cumple solo una parte del criterio de aceptación.
  - *Existente sin verificar:* hay código del laboratorio, pero el criterio depende de algo externo que no comprobé.
  - *No implementado:* no hay código.
- **Evidencia:** archivos de prueba en `tests/` y salidas en `docs/evidencias/`.

## Contradicciones encontradas en Jira

1. **Las asignaciones de Jira no siguen la división oficial.** Por ejemplo, el núcleo de agenda (SCRUM-13, 21, 22) está asignado a 2122100571, el CRUD de servicios y clientas (SCRUM-25, 26) a 2124200352, y a Franc le tocan pantallas Android (SCRUM-60, 61). Seguí la división oficial (backend web = Dominic) y marqué cada caso; **conviene reasignar en Jira**.
2. **SCRUM-54** pide reemplazar Expo por Android Studio, pero el README del repo web todavía dice "React Native + Expo". Por decisión del equipo, la carpeta `android/` de esta entrega solo contiene documentación; el proyecto Android no se creó.
3. **SCRUM-55** (API de registro de clientas para Android) está asignada a Dominic, pero corresponde al backend móvil (José). El registro web (`POST /api/web/auth/registro`) cumple la regla de vincular por teléfono y puede servir de referencia.
4. **SCRUM-58** describe registro móvil con nombre y teléfono, mientras que la web pide correo y contraseña. Falta acordar una sola forma de identificar a la clienta.
5. **SCRUM-35** pide mostrar el costo de la difusión, pero no existe en Jira la tarifa ni dónde configurarla.
6. **Puntos y recompensas** aparecen en la propuesta de la app móvil, pero no hay ninguna historia en Jira. No se implementó.
7. **SCRUM-4 "Subtarea 2.1"** no tiene descripción ni padre; parece un resto de la importación.

## Matriz

| ID | Tarea | Responsable en Jira | Responsable según división oficial | Alcance | Archivos involucrados | Estado real | Evidencia | Pendientes |
|---|---|---|---|---|---|---|---|---|
| SCRUM-11 | Repositorio y Laravel base | Franc | — | Infraestructura | Repo del equipo | Finalizado (previo) | Historial de git | — |
| SCRUM-12 | Plantillas en Meta | Dominic | Dominic | WhatsApp | `config/whatsapp.php`, `app/Console/Commands/WhatsAppPlantillas.php` | Existente sin verificar (laboratorio) | — | Confirmar en Meta que las 3 plantillas están en revisión o aprobadas |
| SCRUM-13 | Esquema y migraciones | 2122100571 | Dominic | Base de datos | `database/migrations/*` | Implementado | Suite en SQLite y PostgreSQL; `migrate:rollback` limpio en `pruebas_postgresql.txt` | Inicio y fin guardados ✔; `wa_id` y `wamid` únicos ✔ |
| SCRUM-14 | Docker y README | Franc | Franc | Infraestructura | — | No implementado | — | Docker Compose |
| SCRUM-15 | PostgreSQL y variables | Franc | Franc / Dominic | Infraestructura | `.env.example`, `config/app.php` | Parcial | Suite pasa en PostgreSQL 16 | Separar local, staging y producción; `.env.example` sigue en SQLite por defecto |
| SCRUM-16 | Webhook del laboratorio | Dominic | Dominic | WhatsApp | `WhatsAppWebhookController.php` | Implementado (portado) | `tests/Feature/WhatsAppWebhookTest.php` | Que Meta verifique la URL del entorno del equipo (requiere URL pública) |
| SCRUM-17 | Envío a Cloud API con errores | Dominic | Dominic | WhatsApp | `app/Services/WhatsApp/WhatsAppService.php` | Parcial | Difusión probada con respuestas simuladas | Traducir los códigos 190, 131047 y 131030 a excepciones propias |
| SCRUM-18 | Autenticación y layout del panel | Franc | Dominic (API) / Franc (layout) | Auth | `AuthController`, `UsuarioController`, `Http/Middleware/*`, `bootstrap/app.php` | Implementado (backend) | `AuthTest`, `PermisosTest` | Layout y pantallas de Franc |
| SCRUM-19 | Seeders de catálogo | 2124200352 | Dominic | Datos de prueba | `database/seeders/*` | Implementado | `migrate:fresh --seed` en evidencias HTTP | Confirmar etapas reales con la dueña |
| SCRUM-20 | Glosario del dominio | 2124200352 | — | Documentación | `BACKEND_WEB_DOMINIC.md` §12 (mínimo) | Parcial | — | Documento propio en el repo |
| SCRUM-21 | Cálculo de disponibilidad | 2122100571 | Dominic | Agenda | `DisponibilidadService.php`, `config/agenda.php` | Implementado, con etapas | `DisponibilidadTest`: borde inicial, borde final, día cerrado, día lleno y pasado | — |
| SCRUM-22 | Servicio de citas | 2122100571 | Dominic | Agenda | `CitaService.php` | Implementado | `CitasTest`; concurrencia con 10 procesos en PostgreSQL | — |
| SCRUM-23 | Vista de agenda día y semana | Franc | Dominic (API) / Franc (UI) | Panel | `AgendaController@index` | Implementado (backend) | `AgendaTest` | Interfaz |
| SCRUM-24 | Crear cita desde el panel | Franc | Dominic (API) / Franc (UI) | Panel | `CitaController@store` | Implementado (backend) | `CitasTest`: 409 con alternativas | Interfaz |
| SCRUM-25 | CRUD de servicios y horarios | 2124200352 | Dominic (API) / Franc (UI) | Panel | `ServicioController`, `HorarioController` | Implementado (backend) | `ServiciosTest`, `AgendaTest` | Interfaz |
| SCRUM-26 | CRUD de clientas y consentimiento | 2124200352 | Dominic (API) / Franc (UI) | Panel | `ClienteController` | Implementado (backend) | `ClientesTest` | Interfaz |
| SCRUM-27 | Alta de clientas desde el webhook | Dominic | Dominic | WhatsApp | `WhatsAppWebhookController.php` | Implementado | `WhatsAppWebhookTest` (reintentos no duplican) | — |
| SCRUM-28 | Máquina de estados de la conversación | Dominic | Dominic | WhatsApp | — | No implementado | — | Fuera de esta entrega |
| SCRUM-29 | Evento de hueco y elegibles | 2122100571 | Dominic | Huecos | `EspacioLiberado`, `RegistrarEspacioLiberado`, `HuecoService` | Implementado | `HuecosTest` | La lista de candidatas se calcula al consultarla; no se guarda aparte |
| SCRUM-30 | CI con estilo y pruebas | Franc | Franc | Calidad | — | No implementado (pipeline) | Pint y la suite pasan localmente | Workflow de GitHub Actions |
| SCRUM-31 | Subdividir el hueco por servicio | 2122100571 | Dominic | Huecos | — | No implementado | — | Calcular sub-bloques |
| SCRUM-32 | WhatsApp: consultar disponibilidad | Dominic | Dominic | WhatsApp | — | No implementado | — | Reutilizar `DisponibilidadService` |
| SCRUM-33 | WhatsApp: confirmar reserva | Dominic | Dominic | WhatsApp | — | No implementado | — | Reutilizar `CitaService` |
| SCRUM-34 | WhatsApp: cancelar | Dominic | Dominic | WhatsApp | — | No implementado | — | Reutilizar `CitaService::cancelar` |
| SCRUM-35 | Pantalla de difusión | Franc | Dominic (API) / Franc (UI) | Huecos | `HuecoController` | Parcial (backend) | `HuecosTest` | Costo estimado; interfaz |
| SCRUM-36 | Enviar difusión y registrar | 2122100571 | Dominic | Huecos | `DifusionService`, tabla `difusiones` | Implementado (sin envío real) | `HuecosTest` con respuestas simuladas de Meta | Envío real cuando se apruebe `espacio_liberado` |
| SCRUM-37 | Cancelar y reprogramar desde el panel | Franc | Dominic (API) / Franc (UI) | Panel | `CitaController@cancelar` y `@update` | Implementado (backend) | `CitasTest` | Interfaz |
| SCRUM-38 | Consentimiento por palabra clave | Dominic | Dominic | WhatsApp | — | No implementado | — | Detectar BAJA en el webhook |
| SCRUM-39 | Actualización en vivo | Franc | Dominic (API) / Franc (UI) | Panel | `AgendaController@cambios` | Implementado (backend) | `AgendaTest` | Sondeo cada 30 s en la interfaz |
| SCRUM-40 | Historial de conversación | 2124200352 | Dominic (API) / Franc (UI) | Panel | `ClienteController@mensajes`, tabla `eventos_whatsapp` | Implementado (backend) | `ClientesTest` | Guardar también las confirmaciones salientes cuando existan |
| SCRUM-41 | Tomar un sub-bloque del anuncio | Dominic | Dominic | WhatsApp | — | No implementado | — | Depende de SCRUM-31 y 28 |
| SCRUM-42 | Evitar difusiones repetidas | 2122100571 | Dominic | Huecos | Índice único en `difusiones`, `HuecoService::sigueLibre` | Implementado | `HuecosTest` | — |
| SCRUM-43 | Recordatorios automáticos | 2122100571 | Dominic | WhatsApp | — | No implementado | — | Tarea programada idempotente |
| SCRUM-44 | Staging con dominio y TLS | Franc | Franc | Infraestructura | — | No implementado | — | — |
| SCRUM-45 | Spike: migrar el número real | Dominic | Dominic | Investigación | — | No implementado | — | — |
| SCRUM-46 | Pruebas de aceptación | 2124200352 | Todos | Calidad | — | No implementado | — | Guion manual |
| SCRUM-47 | Manual de la dueña | 2124200352 | — | Documentación | — | No implementado | — | — |
| SCRUM-48 | Documentación técnica | Franc | Todos | Documentación | `docs/*.md` | Parcial (backend web) | Este documento | README general y despliegue |
| SCRUM-49 | Demo y retrospectiva | Dominic | Todos | Cierre | — | No implementado | — | — |
| SCRUM-54 | Proyecto Android Studio base | Dominic | Rodri | Android | `android/` (solo documentación) | No implementado (decisión del equipo) | — | Reasignar en Jira |
| SCRUM-55 | API de registro de clientas para Android | Dominic | José | Backend móvil | Referencia: `AuthController@registro` | No implementado (es del backend móvil) | La regla de vincular por teléfono está probada en web (`AuthTest`) | Reasignar a José |
| SCRUM-56 | API de servicios y horarios para Android | 2122100571 | José | Backend móvil | Referencia: `ServicioController`, `DisponibilidadController` | No implementado | — | — |
| SCRUM-57 | API de citas de la clienta para Android | 2122100571 | José | Backend móvil | Referencia: `CitaController`, `CitaService` | No implementado | — | — |
| SCRUM-58 | Registro y login en Android | 2124200352 | Rodri | Frontend móvil | — | No implementado | — | — |
| SCRUM-59 | Catálogo en Android | 2124200352 | Rodri | Frontend móvil | — | No implementado | — | — |
| SCRUM-60 | Día y horario en Android | Franc | Rodri | Frontend móvil | — | No implementado | — | Reasignar |
| SCRUM-61 | Confirmar reserva en Android | Franc | Rodri | Frontend móvil | — | No implementado | — | Reasignar |
| SCRUM-62 | Mis citas en Android | 2124200352 | Rodri | Frontend móvil | — | No implementado | — | — |
| SCRUM-63 | APK y prueba en dispositivo | 2124200352 | Rodri | Frontend móvil | — | No implementado | — | — |
| SCRUM-4 | Subtarea 2.1 | — | — | — | — | Sin contenido | — | Borrar o describir |

Las épicas (SCRUM-5 a SCRUM-10 y SCRUM-53) no se listan como filas: su estado se deriva de sus historias.

## Trabajo agregado fuera de una historia específica

Lo pidió explícitamente el alcance del backend web, aunque no tiene historia propia en Jira. Conviene crear las historias para que quede la trazabilidad.

| Funcionalidad | Archivos | Evidencia |
|---|---|---|
| Agenda flexible por etapas (servicios con procesamiento) | `servicio_etapas`, `cita_bloques`, `DisponibilidadService` | `CitasTest`: ejemplo del martes y dos tintes |
| Bloqueos de fechas y horarios | `BloqueoController`, `bloqueos_agenda` | `BloqueosTest` |
| Roles, desactivación y administración de usuarios | `UsuarioController`, `EnsureRol`, `EnsureUsuarioActivo` | `PermisosTest` |
| Registro de visitas (completada o no asistió) | `CitaController@estado`, `CitaService::registrarAtencion` | `CitasTest`, `ClientesTest` |
| Máximo configurable de clientas simultáneas | `config/agenda.php` | `CitasTest` |
