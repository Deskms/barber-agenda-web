@echo off
rem Corre todas las pruebas automaticas y deja el resultado en storage\logs\pruebas.log.
cd /d %~dp0..
C:\php84\php.exe artisan optimize:clear > storage\logs\pruebas.log 2>&1
C:\php84\php.exe artisan test >> storage\logs\pruebas.log 2>&1
echo FIN %ERRORLEVEL% >> storage\logs\pruebas.log
