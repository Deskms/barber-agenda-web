@echo off
rem Aplica la contrasena del panel que esta en el .env (PANEL_ADMIN_PASSWORD).
cd /d %~dp0..
C:\php84\php.exe artisan config:clear > storage\logs\cambio-contrasena.log 2>&1
C:\php84\php.exe artisan db:seed --force >> storage\logs\cambio-contrasena.log 2>&1
C:\php84\php.exe artisan config:cache >> storage\logs\cambio-contrasena.log 2>&1
echo listo >> storage\logs\cambio-contrasena.log
