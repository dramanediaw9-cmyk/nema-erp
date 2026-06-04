param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [int]$KeepBackups = 7
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP introuvable: $PhpPath"
}

Set-Location $ProjectRoot

& $PhpPath artisan config:clear
& $PhpPath artisan migrate --force
& $PhpPath artisan nema:ops:backup-run --keep=$KeepBackups
& $PhpPath artisan nema:ops:backup-verify
& $PhpPath artisan nema:ops:health-check --store
& $PhpPath artisan nema:core:pulse --store
