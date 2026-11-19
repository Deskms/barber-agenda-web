@echo off
rem Arranque para la demo de agendar: carga el catalogo (no duplica) y levanta web, cola y programador.
cd /d %~dp0..
echo Cargando servicios, horarios y clientas de prueba (reejecutable, no duplica)...
C:\php84\php.exe artisan db:seed --class=CatalogoSeeder --force
call "%~dp0iniciar-windows.bat"
echo.
echo Listo: servidor, cola (queue:work) y programador (schedule:work) corriendo en ventanas minimizadas.
pause
