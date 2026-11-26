@echo off
rem Manda a revision de Meta la plantilla de autenticacion codigo_acceso (codigo para entrar a la app).
cd /d %~dp0..
C:\php84\php.exe artisan config:clear > storage\logs\plantilla-codigo.log 2>&1
C:\php84\php.exe artisan whatsapp:plantillas --crear --solo=codigo_acceso >> storage\logs\plantilla-codigo.log 2>&1
C:\php84\php.exe artisan whatsapp:plantillas >> storage\logs\plantilla-codigo.log 2>&1
type storage\logs\plantilla-codigo.log
pause
