@echo off
rem Cierra la demo: servidor, cola y programador abiertos por iniciar-windows.bat.
cd /d %~dp0..
taskkill /F /T /FI "WINDOWTITLE eq BarberAgenda*" > storage\logs\detener.log 2>&1
timeout /t 2 /nobreak >nul
echo --- php restantes: >> storage\logs\detener.log
tasklist /FI "IMAGENAME eq php.exe" >> storage\logs\detener.log 2>&1
