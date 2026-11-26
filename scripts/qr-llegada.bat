@echo off
rem Escribe en storage\logs\qr-llegada.txt la direccion que va en el QR de llegada del mostrador.
cd /d %~dp0..
C:\php84\php.exe artisan tinker --execute="echo app(App\Services\Agenda\Llegadas::class)->url();" > storage\logs\qr-llegada.txt 2>&1
echo. >> storage\logs\qr-llegada.txt
curl.exe -s -o nul -w "local %%{http_code}\n" http://127.0.0.1:8010/login >> storage\logs\qr-llegada.txt 2>&1
curl.exe -s -o nul -w "tunel %%{http_code}\n" https://citas.tunegociomx.app/login >> storage\logs\qr-llegada.txt 2>&1
curl.exe -s -o nul -w "landing %%{http_code}\n" http://127.0.0.1:8010/landing/index.html >> storage\logs\qr-llegada.txt 2>&1
echo listo >> storage\logs\qr-llegada.txt
