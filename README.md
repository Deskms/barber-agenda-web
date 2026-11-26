# Barber Agenda — Web y API

Sistema de citas para **Joss Barber Salón** (estética y barbería, La Loma, Guadalajara). Este repositorio contiene el **panel web** de la dueña, el **servidor / API** en Laravel (el único componente que escribe en la base de datos), el **asistente de WhatsApp**, la **landing page** y la **presentación** del proyecto.

Proyecto Cuatrimestral Integral · Ingeniería en Desarrollo y Gestión de Software · UTJ · 2026C · 9.° B · Equipo Upgrade.

## Componentes

| Componente | Tecnología | Dónde |
|---|---|---|
| Panel web de la dueña | Laravel + Blade + Bootstrap (responsivo para iPad y celular) | este repositorio |
| Servidor / API | Laravel 13 sobre PHP 8.4 | este repositorio |
| Base de datos | MariaDB (las pruebas automáticas usan SQLite en memoria) | migraciones en `database/` |
| Asistente de WhatsApp | WhatsApp Business Platform (Cloud API de Meta) | `app/Services/Bot`, `app/Services/WhatsApp` |
| Landing page | HTML, CSS y JavaScript | `public/landing` |
| Presentación del proyecto | HTML, CSS y JavaScript | `public/presentacion` |
| App Android para clientas | Kotlin + Jetpack Compose | repositorio `barber-agenda-mobile` |

Regla central: **toda la lógica de disponibilidad vive en el servidor**. El panel, la app y WhatsApp solo consumen la API, así que ningún canal puede confirmar una cita empalmada.

## Requisitos

- PHP 8.4 y Composer
- MariaDB 10.6 o superior

## Arranque local

```bash
composer install
cp .env.example .env          # llenar DB_* y, si se usa, WHATSAPP_*
php artisan key:generate
php artisan migrate
php artisan db:seed --class=CatalogoSeeder
php artisan serve --port=8010
php artisan queue:work         # en otra terminal: mensajes de WhatsApp
php artisan schedule:work      # en otra terminal: recordatorios y avisos
```

En Windows, `scripts\demo-agendar.bat` hace todo lo anterior y `scripts\detener-windows.bat` lo apaga.

## Pruebas

```bash
php artisan test
```

88 pruebas automáticas (367 comprobaciones) en verde al cierre del cuatrimestre.

## Documentación

En `docs/`: arquitectura, API móvil (`API_MOVIL.md` y `openapi.yaml`), guía de uso y entrega, entorno de pruebas, pruebas y presentación, e informe final.

## Restricción de pruebas con WhatsApp

El WhatsApp del negocio es su canal principal de clientas. **Todas las pruebas se hacen con el número de prueba proporcionado por Meta**; no se conecta, migra ni modifica la cuenta real. Las credenciales viven solo en `.env`, nunca en el repositorio.

## Equipo

| Integrante | Participación |
|---|---|
| Adrián Dominic Hernández Esqueda | Líder del proyecto. Desarrollo del sistema: servidor, panel web, asistente de WhatsApp, API y app Android; pruebas, puesta en marcha y demostración. |
| José Francisco Quezada Palomo | Co-desarrollo junto con Dominic; diseño visual de la app Android, landing page y presentación web. |
| José Manuel Castellanos Castro | Versión inicial (V1) del QR y del backend móvil. Resultó insuficiente y se tomó como guía para la versión final. |
| Rodrigo Salas Martínez | No participó en el desarrollo. |
