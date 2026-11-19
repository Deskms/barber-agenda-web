@echo off
rem Regenera las vistas en cache despues de cambiar archivos .blade.php.
cd /d %~dp0..
C:\php84\php.exe artisan view:clear > storage\logs\vistas.log 2>&1
C:\php84\php.exe artisan view:cache >> storage\logs\vistas.log 2>&1
echo listo >> storage\logs\vistas.log
