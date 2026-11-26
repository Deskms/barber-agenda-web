@echo off
rem Vuelve a leer el .env (por ejemplo, despues de agregar GOOGLE_CLIENT_ID). No detiene el sistema.
cd /d %~dp0..
C:\php84\php.exe artisan config:cache > storage\logs\config.log 2>&1
echo listo >> storage\logs\config.log
