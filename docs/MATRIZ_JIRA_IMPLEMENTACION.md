# Matriz Jira ↔ implementación

Fuente: proyecto **SCRUM** en barberagenda.atlassian.net, leído el 3 de noviembre de 2026 (57 issues). **No se modificó ningún estado en Jira.**

**Alcance acordado:** Barber Agenda es una **página web + app Android**. **No usa WhatsApp.** Las historias de Jira que dependen de WhatsApp quedan marcadas como fuera de alcance.

## Cómo leer esta matriz

- **Responsable en Jira:** la persona asignada hoy en Jira. Dos integrantes aparecen con su matrícula (2122100571 y 2124200352); no adiviné a quién corresponde cada una.
- **Responsable según la división oficial:** Dominic = backend web, Franc = frontend web, José = backend móvil, Rodri = frontend móvil.
- **Estado real:** lo verifiqué en la rama `feature/backend-web-dominic`.
  - *Implementado:* hay código **y** prueba que lo respalda.
  - *Implementado (backend):* la parte de servidor está lista y probada; falta la interfaz de otro integrante.
  - *Parcial:* cumple solo una parte del criterio de aceptación.
  - *No implementado:* no hay código.
  - *Fuera de alcance:* depende de WhatsApp.

## Acciones recomendadas en Jira

1. **Cerrar o reescribir la épica SCRUM-6 "Implementar integración con WhatsApp", la épica SCRUM-7 "Desarrollar conversación y reserva" y sus historias.** Todas dependen de WhatsApp.
2. **Reescribir las historias de huecos para la app.** SCRUM-35, 36, 41 y 42 describen difusión por WhatsApp; sin WhatsApp, el aviso de citas de última hora lo daría la app Android. Lo mismo aplica a los recordatorios (SCRUM-43).
3. **Reasignar según la división oficial.** El núcleo de agenda (SCRUM-13, 21, 22) está asignado a 2122100571, el CRUD (SCRUM-25, 26) a 2124200352 y pantallas Android a Franc (SCRUM-60, 61).
4. **SCRUM-54** pide reemplazar Expo por Android Studio. Por decisión del equipo, la carpeta `android/` de esta entrega solo contiene documentación.
5. **SCRUM-55** (API de registro de clientas para Android) está asignada a Dominic, pero corresponde al backend móvil (José).
6. **Puntos y recompensas** aparecen en la propuesta de la app, pero no tienen historia en Jira.
7. **SCRUM-4 "Subtarea 2.1"** no tiene descripción ni padre.

## Matriz

| ID | Tarea | Responsable en Jira | Responsable según división oficial | Alcance | Archivos involucrados | Estado real | Evidencia | Pendientes |
|---|---|---|---|---|---|---|---|---|
| SCRUM-11 | Repositorio y Laravel base | Franc | — | Infraestructura | Repo del equipo | Finalizado (previo) | Historial de git | — |
| SCRUM-12 | Plantillas en Meta | Dominic | — | WhatsApp | — | Fuera de alcance | — | Cerrar en Jira |
| SCRUM-13 | Esquema y migraciones | 2122100571 | Dominic | Base de datos | `database/migrations/*` | Implementado | Suite en SQLite y PostgreSQL; `migrate:fresh --seed` y `migrate:rollback` en `pruebas_postgresql.txt` | — |
| SCRUM-14 | Docker y README | Franc | Franc | Infraestructura | — | No implementado | — | Docker Compose |
| SCRUM-15 | PostgreSQL y variables | Franc | Franc / Dominic | Infraestructura | `.env.example`, `config/app.php` | Parcial | Suite pasa en PostgreSQL 16 | Separar local, staging y producción; `.env.example` sigue en SQLite por defecto |
| SCRUM-16 | Webhook del laboratorio | Dominic | — | WhatsApp | Retirado del repo | Fuera de alcance | — | Cerrar en Jira |
| SCRUM-17 | Envío a Cloud API | Dominic | — | WhatsApp | Retirado del repo | Fuera de alcance | — | Cerrar en Jira |
| SCRUM-18 | Autenticación y layout del panel | Franc | Dominic (API) / Franc (layout) | Auth | `AuthController`, `UsuarioController`, `Http/Middleware/*`, `bootstrap/app.php` | Implementado (backend) | `AuthTest`, `PermisosTest` | Layout de Franc |
| SCRUM-19 | Seeders de catálogo | 2124200352 | Dominic | Datos de prueba | `database/seeders/*` | Implementado | `migrate:fresh --seed` en PostgreSQL | Confirmar etapas reales con la dueña |
| SCRUM-20 | Glosario del dominio | 2124200352 | — | Documentación | `BACKEND_WEB_DOMINIC.md` §12 (mínimo) | Parcial | — | Documento propio sin términos de WhatsApp |
| SCRUM-21 | Cálculo de disponibilidad | 2122100571 | Dominic | Agenda | `DisponibilidadService.php`, `config/agenda.php` | Implementado, con etapas | `DisponibilidadTest`: borde inicial, borde final, día cerrado, día lleno y pasado | — |
| SCRUM-22 | Servicio de citas | 2122100571 | Dominic | Agenda | `CitaService.php` | Implementado | `CitasTest`; concurrencia con 10 procesos en PostgreSQL | — |
| SCRUM-23 | Vista de agenda día y semana | Franc | Dominic (API) / Franc (UI) | Panel | `AgendaController@index` | Implementado (backend) | `AgendaTest` | Interfaz |
| SCRUM-24 | Crear cita desde el panel | Franc | Dominic (API) / Franc (UI) | Panel | `CitaController@store` | Implementado (backend) | `CitasTest`: 409 con alternativas | Interfaz |
| SCRUM-25 | CRUD de servicios y horarios | 2124200352 | Dominic (API) / Franc (UI) | Panel | `ServicioController`, `HorarioController` | Implementado (backend) | `ServiciosTest`, `AgendaTest` | Interfaz |
| SCRUM-26 | CRUD de clientas y consentimiento | 2124200352 | Dominic (API) / Franc (UI) | Panel | `ClienteController` | Implementado (backend) | `ClientesTest` | Interfaz |
| SCRUM-27 | Alta de clientas desde el webhook | Dominic | — | WhatsApp | — | Fuera de alcance | — | Cerrar en Jira; las clientas se dan de alta en la web, la app o el panel |
| SCRUM-28 | Máquina de estados de la conversación | Dominic | — | WhatsApp | — | Fuera de alcance | — | Cerrar en Jira |
| SCRUM-29 | Evento de hueco y elegibles | 2122100571 | Dominic | Huecos | `EspacioLiberado`, `RegistrarEspacioLiberado`, `HuecoService` | Implementado | `HuecosTest` | — |
| SCRUM-30 | CI con estilo y pruebas | Franc | Franc | Calidad | — | No implementado (pipeline) | Pint y la suite pasan localmente | Workflow de GitHub Actions |
| SCRUM-31 | Subdividir el hueco por servicio | 2122100571 | Dominic | Huecos | — | No implementado | — | Calcular sub-bloques |
| SCRUM-32 | WhatsApp: consultar disponibilidad | Dominic | — | WhatsApp | — | Fuera de alcance | — | En la app es SCRUM-60 |
| SCRUM-33 | WhatsApp: confirmar reserva | Dominic | — | WhatsApp | — | Fuera de alcance | — | En la app es SCRUM-61 |
| SCRUM-34 | WhatsApp: cancelar | Dominic | — | WhatsApp | — | Fuera de alcance | — | En la app es SCRUM-62 |
| SCRUM-35 | Pantalla de difusión de hueco | Franc | Franc (UI) | Huecos | `HuecoController` (lista y candidatas) | Por redefinir | `HuecosTest` | Sin WhatsApp no hay difusión; la pantalla puede mostrar huecos para agendar desde el panel |
| SCRUM-36 | Enviar difusión y registrar | 2122100571 | José / Rodri | Huecos | — | Por redefinir | — | El aviso lo daría la app (notificación o pantalla de huecos) |
| SCRUM-37 | Cancelar y reprogramar desde el panel | Franc | Dominic (API) / Franc (UI) | Panel | `CitaController@cancelar` y `@update` | Implementado (backend) | `CitasTest` | Interfaz |
| SCRUM-38 | Consentimiento por palabra clave | Dominic | — | WhatsApp | — | Fuera de alcance | — | El consentimiento se edita en el panel y en la cuenta de la clienta |
| SCRUM-39 | Actualización en vivo | Franc | Dominic (API) / Franc (UI) | Panel | `AgendaController@cambios` | Implementado (backend) | `AgendaTest` | Sondeo cada 30 s en la interfaz |
| SCRUM-40 | Historial de conversación | 2124200352 | — | WhatsApp | — | Fuera de alcance | — | El historial de visitas sí existe (`/admin/clientes/{id}/historial`) |
| SCRUM-41 | Tomar un sub-bloque del anuncio | Dominic | José / Rodri | Huecos | — | Por redefinir | — | Depende de SCRUM-31 y del aviso en la app |
| SCRUM-42 | Evitar avisos repetidos y huecos vendidos | 2122100571 | Dominic | Huecos | `HuecoService::sigueLibre` | Parcial | `HuecosTest` (`sigue_libre`) | Evitar avisos repetidos cuando exista el aviso en la app |
| SCRUM-43 | Recordatorios automáticos | 2122100571 | Por definir | Recordatorios | — | Por redefinir | — | Sin WhatsApp: notificación de la app o correo |
| SCRUM-44 | Staging con dominio y TLS | Franc | Franc | Infraestructura | — | No implementado | — | — |
| SCRUM-45 | Spike: migrar el número real | Dominic | — | WhatsApp | — | Fuera de alcance | — | Cerrar en Jira |
| SCRUM-46 | Pruebas de aceptación | 2124200352 | Todos | Calidad | — | No implementado | — | Guion manual web y app |
| SCRUM-47 | Manual de la dueña | 2124200352 | — | Documentación | — | No implementado | — | — |
| SCRUM-48 | Documentación técnica | Franc | Todos | Documentación | `docs/*.md` | Parcial (backend web) | Este documento | README general y despliegue |
| SCRUM-49 | Demo y retrospectiva | Dominic | Todos | Cierre | — | No implementado | — | — |
| SCRUM-54 | Proyecto Android Studio base | Dominic | Rodri | Android | `android/` (solo documentación) | No implementado (decisión del equipo) | — | Reasignar |
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
