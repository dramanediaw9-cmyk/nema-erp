param(
    [string]$ProjectRoot = '',
    [string]$ServerTaskName = 'Nema ERP Server',
    [string]$WorkersTaskName = 'Nema ERP Workers',
    [string]$MaintenanceTaskName = 'Nema ERP Daily Maintenance',
    [string]$MaintenanceTime = '02:45'
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

$serverScript = Join-Path $ProjectRoot 'scripts\start-nema-erp.ps1'
$workersScript = Join-Path $ProjectRoot 'scripts\start-nema-workers.ps1'
$maintenanceScript = Join-Path $ProjectRoot 'scripts\run-nema-maintenance.ps1'

function Register-Task($Name, $Trigger, $ScriptPath) {
    $action = New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`""

    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -MultipleInstances IgnoreNew

    Register-ScheduledTask `
        -TaskName $Name `
        -Action $action `
        -Trigger $Trigger `
        -Settings $settings `
        -Description 'Nema ERP automation task' `
        -Force | Out-Null
}

$maintenanceParts = $MaintenanceTime -split ':', 2
$maintenanceAt = (Get-Date).Date.AddHours([int]$maintenanceParts[0]).AddMinutes([int]$maintenanceParts[1])

try {
    Register-Task -Name $ServerTaskName -Trigger (New-ScheduledTaskTrigger -AtLogOn) -ScriptPath $serverScript
    Register-Task -Name $WorkersTaskName -Trigger (New-ScheduledTaskTrigger -AtLogOn) -ScriptPath $workersScript
    Register-Task -Name $MaintenanceTaskName -Trigger (New-ScheduledTaskTrigger -Daily -At $maintenanceAt) -ScriptPath $maintenanceScript

    Write-Host "[OK] Tache installee: $ServerTaskName (ouverture de session)"
    Write-Host "[OK] Tache installee: $WorkersTaskName (ouverture de session)"
    Write-Host "[OK] Tache installee: $MaintenanceTaskName ($MaintenanceTime)"
} catch {
    $startupRoot = [Environment]::GetFolderPath('Startup')
    $serverStartupCommand = Join-Path $startupRoot 'Nema ERP Server.cmd'
    $startupCommand = Join-Path $startupRoot 'Nema ERP Workers.cmd'
    $serverCommand = "@echo off`r`npowershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$serverScript`" -SkipBrowser`r`n"
    $command = "@echo off`r`npowershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$workersScript`"`r`n"

    Set-Content -Path $serverStartupCommand -Value $serverCommand -Encoding ASCII
    Set-Content -Path $startupCommand -Value $command -Encoding ASCII

    Write-Host "[ATTENTION] Taches Windows non installees: $($_.Exception.Message)"
    Write-Host "[OK] Fallback installe dans le dossier Demarrage: $serverStartupCommand"
    Write-Host "[OK] Fallback installe dans le dossier Demarrage: $startupCommand"
    Write-Host "[INFO] Le serveur et les workers seront relances a l'ouverture de session. Le scheduler Laravel executera les maintenances planifiees."
}
