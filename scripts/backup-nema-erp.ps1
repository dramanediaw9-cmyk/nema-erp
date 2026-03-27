param(
    [string]$ProjectRoot = '',
    [string]$BackupRoot = '',
    [string]$MysqlBin = 'C:\xampp\mysql\bin',
    [string]$Database = 'nema_erp',
    [string]$Username = 'root',
    [string]$Password = ''
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

if ([string]::IsNullOrWhiteSpace($BackupRoot)) {
    $BackupRoot = Join-Path $ProjectRoot 'backups'
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$target = Join-Path $BackupRoot $timestamp
New-Item -ItemType Directory -Force -Path $target | Out-Null

$dumpArgs = @('-u', $Username)
if (-not [string]::IsNullOrEmpty($Password)) {
    $dumpArgs += "-p$Password"
}
$dumpArgs += $Database

$sqlPath = Join-Path $target 'database.sql'
& (Join-Path $MysqlBin 'mysqldump.exe') @dumpArgs | Set-Content -Path $sqlPath -Encoding UTF8
if ($LASTEXITCODE -ne 0) {
    throw 'La sauvegarde MySQL a echoue.'
}

$storageArchive = Join-Path $target 'storage.zip'
$storagePaths = @(
    (Join-Path $ProjectRoot 'storage\app')
    (Join-Path $ProjectRoot 'storage\logs')
) | Where-Object { Test-Path $_ }
if ($storagePaths.Count -gt 0) {
    Compress-Archive -Path $storagePaths -DestinationPath $storageArchive -Force
}

$envExamplePath = Join-Path $ProjectRoot '.env.example'
if (Test-Path $envExamplePath) {
    Copy-Item -Path $envExamplePath -Destination (Join-Path $target '.env.example') -Force
}

$manifest = [ordered]@{
    created_at = (Get-Date).ToString('s')
    project_root = $ProjectRoot
    database = $Database
    backup_path = $target
    files = @('database.sql', 'storage.zip', '.env.example')
}
$manifest | ConvertTo-Json -Depth 4 | Set-Content -Path (Join-Path $target 'manifest.json') -Encoding UTF8

Write-Host "Sauvegarde terminee : $target"
