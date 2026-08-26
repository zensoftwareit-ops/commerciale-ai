$ErrorActionPreference = 'Stop'

$wordpressRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$dist = Join-Path $wordpressRoot 'dist'
$theme = Join-Path $wordpressRoot 'commerciale-ai-theme'
$plugin = Join-Path $wordpressRoot 'commerciale-ai-client'
$formsPlugin = Join-Path $wordpressRoot 'commerciale-ai-forms'

New-Item -ItemType Directory -Path $dist -Force | Out-Null

$themeZip = Join-Path $dist 'commerciale-ai-theme.zip'
$pluginZip = Join-Path $dist 'commerciale-ai-client.zip'
$formsPluginZip = Join-Path $dist 'commerciale-ai-forms.zip'

if (Test-Path -LiteralPath $themeZip) { Remove-Item -LiteralPath $themeZip }
if (Test-Path -LiteralPath $pluginZip) { Remove-Item -LiteralPath $pluginZip }
if (Test-Path -LiteralPath $formsPluginZip) { Remove-Item -LiteralPath $formsPluginZip }

Compress-Archive -Path $theme -DestinationPath $themeZip -CompressionLevel Optimal
Compress-Archive -Path $plugin -DestinationPath $pluginZip -CompressionLevel Optimal
Compress-Archive -Path $formsPlugin -DestinationPath $formsPluginZip -CompressionLevel Optimal

Get-Item -LiteralPath $themeZip, $pluginZip, $formsPluginZip | Select-Object Name, Length, LastWriteTime
