param(
    [string]$ServerTaskName = 'Nema ERP Server',
    [string]$WorkersTaskName = 'Nema ERP Workers',
    [string]$MaintenanceTaskName = 'Nema ERP Daily Maintenance'
)

$ErrorActionPreference = 'Stop'

foreach ($taskName in @($ServerTaskName, $WorkersTaskName, $MaintenanceTaskName)) {
    schtasks.exe /Delete /F /TN $taskName 2>$null | Out-Null
    Write-Host "[OK] Tache supprimee si elle existait: $taskName"
}

foreach ($startupName in @('Nema ERP Server.cmd', 'Nema ERP Workers.cmd')) {
    $startupCommand = Join-Path ([Environment]::GetFolderPath('Startup')) $startupName
    if (Test-Path $startupCommand) {
        Remove-Item -LiteralPath $startupCommand -Force
        Write-Host "[OK] Lanceur Demarrage supprime: $startupCommand"
    }
}
