@echo off
rem Instala las librerias de exportacion: dompdf (PDF) y OpenSpout (Excel .xlsx).
rem Usa el PHP 8.4 del proyecto, no el 8.2 de XAMPP.
cd /d %~dp0..
C:\php84\php.exe C:\composer\composer.phar require "dompdf/dompdf:^3.1" "openspout/openspout:^4.24" --no-interaction > storage\logs\composer.log 2>&1
echo FIN %ERRORLEVEL% >> storage\logs\composer.log
