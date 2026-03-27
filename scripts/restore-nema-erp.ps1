param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,
    [string]$ProjectRoot = '',
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

$sqlPath = Join-Path $BackupPath 'database.sql'
if (-not (Test-Path $sqlPath)) {
    throw 'Fichier database.sql introuvable dans la sauvegarde.'
}

$mysqlArgs = @('-u', $Username)
if (-not [string]::IsNullOrEmpty($Password)) {
    $mysqlArgs += "-p$Password"
}
$mysqlArgs += $Database

Get-Content -Path $sqlPath -Raw | & (Join-Path $MysqlBin 'mysql.exe') @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    throw 'La restauration MySQL a echoue.'
}

$storageArchive = Join-Path $BackupPath 'storage.zip'
if (Test-Path $storageArchive) {
    $storageRoot = Join-Path $ProjectRoot 'storage'
    if (Test-Path $storageRoot) {
        Remove-Item -Recurse -Force -Path (Join-Path $storageRoot 'app') -ErrorAction SilentlyContinue
        Remove-Item -Recurse -Force -Path (Join-Path $storageRoot 'logs') -ErrorAction SilentlyContinue
    }
    Expand-Archive -Path $storageArchive -DestinationPath $ProjectRoot -Force
}

Write-Host "Restauration terminee depuis : $BackupPath"
