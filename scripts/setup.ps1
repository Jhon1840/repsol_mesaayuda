param([switch]$Demo)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path $PSScriptRoot -Parent)
function Invoke-Docker {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw "Docker falló con código $LASTEXITCODE" }
}
if (!(Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
Invoke-Docker compose build laravel.test
Invoke-Docker compose up -d mysql
Invoke-Docker compose run --rm laravel.test composer install --no-interaction
Invoke-Docker compose run --rm --no-deps vite npm ci
Invoke-Docker compose run --rm --no-deps vite npm run build
if (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=\s*$' -Quiet) {
    Invoke-Docker compose run --rm laravel.test php artisan key:generate --no-interaction
}
Invoke-Docker compose run --rm laravel.test php artisan migrate --seed --no-interaction
if ($Demo) { Invoke-Docker compose run --rm laravel.test php artisan db:seed --class=DemoSeeder --no-interaction }
Invoke-Docker compose up -d laravel.test worker
Write-Host 'Mesa de ayuda disponible en http://localhost:8088'
