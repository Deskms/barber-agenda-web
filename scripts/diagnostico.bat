@echo off
rem Diagnostico para instalar librerias: donde esta composer y que version de PHP usa.
cd /d %~dp0..
where composer > storage\logs\diagnostico.log 2>&1
call composer -V >> storage\logs\diagnostico.log 2>&1
C:\php84\php.exe -v >> storage\logs\diagnostico.log 2>&1
C:\php84\php.exe -m >> storage\logs\diagnostico.log 2>&1
echo listo >> storage\logs\diagnostico.log
