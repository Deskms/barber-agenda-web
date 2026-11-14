# Integración del backend móvil — para Rodrigo (App Android)

Este documento explica cómo consumir el backend móvil de Barber Agenda (SCRUM-55 a SCRUM-57) desde la aplicación Android. Todos los ejemplos corresponden a respuestas reales verificadas mediante la suite automatizada de pruebas (`Tests\Feature\Movil\ApiMovilTest`).

## 1. URL base y conexión

| Entorno | URL base |
|---|---|
| Local (Emulador Android) | `http://10.0.2.2:8000/api/movil` (con `php artisan serve --host=0.0.0.0`) |
| Local (Dispositivo físico / PC) | `http://<IP_LOCAL>:8000/api/movil` |
| Staging | Pendiente (SCRUM-44) |

Encabezados en **todas** las peticiones:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>   ← solo en rutas protegida