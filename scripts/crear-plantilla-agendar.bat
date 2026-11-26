@echo off
rem Manda a revision de Meta la plantilla de espacio libre que dice "responde AGENDAR".
rem Mientras Meta no la apruebe, el sistema sigue usando la anterior.
cd /d %~dp0..
C:\php84\php.exe artisan whatsapp:plantillas --crear --solo=espacio_liberado_agendar > storage\logs\plantilla-agendar.log 2>&1
C:\php84\php.exe artisan whatsapp:plantillas >> storage\logs\plantilla-agendar.log 2>&1
type storage\logs\plantilla-agendar.log
pause
