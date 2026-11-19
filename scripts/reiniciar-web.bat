@echo off
rem Vuelve a levantar SOLO el servidor web (puerto 8010) sin duplicar la cola ni el programador.
cd /d %~dp0..
set PHP_CLI_SERVER_WORKERS=4
start "BarberAgenda web" /min C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8010 --no-reload
