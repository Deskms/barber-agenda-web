# Barber Agenda — Web y API

Sistema de gestión de citas para **Joss Estética y Barbería** (Guadalajara, Jalisco). Este repositorio contiene el **panel web** de la dueña y el **Backend/API** en Laravel, que es el único componente autorizado para escribir en la base de datos.

Proyecto Cuatrimestral Integral · Ingeniería en Desarrollo y Gestión de Software · UTJ · 2026C · 9.° B · Equipo 1.

## Componentes

| Componente | Tecnología | Repositorio |
|---|---|---|
| Panel web | Laravel + Blade + Bootstrap | este |
| Backend / API | Laravel 13 sobre PHP 8.4, monolito modular | este |
| Base de datos | PostgreSQL | — |
| App móvil | Android Studio (nativa) | [barber-agenda-mobile](../barber-agenda-mobile) |

La regla de diseño central: **toda la lógica de disponibilidad vive en el Backend**. La página web y la app Android solo consumen la API, así que ningún canal puede confirmar una cita empalmada.

## Requisitos

- PHP 8.4 y Composer
- Node.js 20 o superior
- PostgreSQL 16

## Arranque local

```bash
git clone <url-del-repo> barber-agenda-web
cd barber-agenda-web
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan serve
```

> La configuración de PostgreSQL y el entorno con Docker se documentarán en este README al cerrar SCRUM-14 y SCRUM-15.

## Forma de trabajo

- Gestión: Jira, proyecto SCRUM (4 sprints de 5 días).
- Comunicación: Discord, con un canal por tema y `#decisiones` para acuerdos.
- Código: ver [CONTRIBUTING.md](CONTRIBUTING.md) para ramas, commits y pull requests.

## Equipo

| Integrante | Rol |
|---|---|
| Adrián Dominic Hernández Esqueda | Líder de proyecto · backend web |
| José Francisco Quezada Palomo | Desarrollador fullstack · panel, CI y despliegue |
| José Manuel Castellanos Castro | Desarrollador backend · datos y disponibilidad |
| Rodrigo Salas Martínez | Desarrollador en formación · catálogos, pruebas y documentación |

## Backend web (API para el frontend)

La API del backend web vive bajo `/api/web` y usa tokens Bearer de Sanctum.

| Documento | Contenido |
|---|---|
| [docs/BACKEND_WEB_DOMINIC.md](docs/BACKEND_WEB_DOMINIC.md) | Arquitectura, base de datos, reglas de la agenda por etapas, endpoints, ejecución, pruebas y pendientes |
| [docs/INTEGRACION_FRONTEND_WEB_FRANC.md](docs/INTEGRACION_FRONTEND_WEB_FRANC.md) | Contrato de la API con ejemplos reales para el frontend web |
| [docs/MATRIZ_JIRA_IMPLEMENTACION.md](docs/MATRIZ_JIRA_IMPLEMENTACION.md) | Estado real de cada historia de Jira |
| [docs/evidencias/](docs/evidencias/) | Salida de las pruebas en SQLite y PostgreSQL, concurrencia e integración HTTP |

Primeros pasos después de `composer install`:

```bash
php artisan migrate --seed
php artisan usuarios:crear-admin duena@ejemplo.com --nombre="Joss"
php artisan test
```
