param(
    [int]$Port = 8000,
    [switch]$StopMysqlXampp
)

$ErrorActionPreference = 'Stop'

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

function Stop-ProcessByPort($PortNumber) {
    $listeners = Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -eq $PortNumber } | Select-Object -ExpandProperty OwningProcess -Unique

    if (-not $listeners) {
        Write-Step "Aucun processus en ecoute sur le port $PortNumber."
        return
    }

    foreach ($processId in $listeners) {
        try {
            $process = Get-Process -Id $processId -ErrorAction Stop
            Stop-Process -Id $processId -Force -ErrorAction Stop
            Write-Step "Processus arrete sur le port $PortNumber : $($process.ProcessName) ($processId)"
        } catch {
            Write-Step "Impossible d arreter le processus $processId sur le port $PortNumber."
        }
    }
}

function Stop-XamppMysql() {
    $xamppMysql = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -eq 'mysqld.exe' -and $_.ExecutablePath -like 'C:\xampp\mysql\bin\*' }

    if (-not $xamppMysql) {
        Write-Step 'Aucun processus MariaDB XAMPP detecte.'
        return
    }

    foreach ($process in $xamppMysql) {
        try {
            Stop-Process -Id $process.ProcessId -Force -ErrorAction Stop
            Write-Step "MariaDB XAMPP arretee : PID $($process.ProcessId)"
        } catch {
            Write-Step "Impossible d arreter MariaDB XAMPP : PID $($process.ProcessId)"
        }
    }
}

Write-Host 'Arret local Nema ERP'
Write-Host '--------------------'

Stop-ProcessByPort -PortNumber $Port

if ($StopMysqlXampp) {
    Stop-XamppMysql
}
