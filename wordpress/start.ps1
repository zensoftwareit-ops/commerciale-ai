$ErrorActionPreference = 'Stop'
$wordpressRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$environmentFile = Join-Path $wordpressRoot '.env'

if (-not (Test-Path -LiteralPath $environmentFile)) {
    Copy-Item -LiteralPath (Join-Path $wordpressRoot '.env.example') -Destination $environmentFile
    Write-Host 'Creato wordpress/.env. Compila password, URL e chiavi, poi riesegui lo script.'
    exit 1
}

Push-Location $wordpressRoot
try {
    docker compose up -d db wordpress
    docker compose run --rm wpcli
    docker compose up -d
} finally {
    Pop-Location
}
