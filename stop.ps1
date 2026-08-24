$ErrorActionPreference = 'Stop'
$wordpressRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Push-Location $wordpressRoot
try {
    docker compose down
} finally {
    Pop-Location
}
