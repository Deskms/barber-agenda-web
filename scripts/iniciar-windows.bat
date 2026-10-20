@echo off
rem Levanta Barber Agenda en esta PC para el entorno de pruebas.
rem   - Web en http://127.0.0.1:8010 (solo local; el tunel de Cloudflare la publica)
rem   - Cola: respuestas del bot de WhatsApp
rem   - Programador: recordatorios de citas
rem Usa PHP 8.4 de C:\php84 porque Laravel 13 no corre en el PHP 8.2 de XAMPP.

set PHP=C:\php84\php.exe
set PHP_CLI_SERVER_WORKERS=4
cd /d %~dp0..

"%PHP%" artisan config:cache >nul
"%PHP%" artisan route:cache >nul
"%PHP%" artisan view:cache >nul

start "BarberAgenda web" /min "%PHP%" artisan serve --host=127.0.0.1 --port=8010 --no-reload
start "BarberAgenda cola" /min "%PHP%" artisan queue:work --tries=1 --timeout=60 --sleep=1
start "BarberAgenda programador" /min "%PHP%" artisan schedule:work

echo Barber Agenda iniciada en http://127.0.0.1:8010
