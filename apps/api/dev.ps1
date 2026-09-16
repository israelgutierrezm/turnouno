# Arranque de desarrollo RAPIDO (Windows / PowerShell).
#
# El cuello de botella en dev NO es la app: es que el PHP de WAMP carga Xdebug en
# modo "develop" (~1s de sobrecarga POR request) y OPcache esta apagado. Con Xdebug
# apagado + OPcache el arranque de cada request baja de ~1.1s a ~0.27s (4x), y la
# Agenda (que dispara varias llamadas) deja de tardar.
#
# Uso:  ./dev.ps1        (levanta server + queue + logs + vite, como `composer dev`)
#       ./dev.ps1 -Solo  (solo el server de la API, en :8000)

param([switch]$Solo)

Set-Location $PSScriptRoot

# Apaga Xdebug SOLO para estos procesos (no toca tu php.ini global; sigue disponible
# cuando de verdad quieras depurar).
$env:XDEBUG_MODE = 'off'

if ($Solo) {
    php -d opcache.enable=1 -d opcache.enable_cli=1 artisan serve --port=8000
}
else {
    composer dev
}
