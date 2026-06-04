param(
    [string]$ProjectRoot = ''
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

$runtimeRoot = Join-Path $ProjectRoot 'storage\app\runtime'
$pidFiles = @(
    Join-Path $runtimeRoot 'nema-queue-worker.pid'
    Join-Path $runtimeRoot 'nema-scheduler-worker.pid'
)

Write-Host 'Arret workers Nema ERP'
Write-Host '----------------------'

foreach ($pidPath in $pidFiles) {
    if (-not (Test-Path $pidPath)) {
        continue
    }

    $pidValue = Get-Content $pidPath -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($pidValue) {
        $process = Get-Process -Id ([int]$pidValue) -ErrorAction SilentlyContinue
        if ($process) {
            Stop-Process -Id $process.Id -Force
            Write-Step "Processus $($process.Id) arrete."
        }
    }

    Remove-Item -LiteralPath $pidPath -Force
}

$workerProcesses = Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -eq 'php.exe' -and
        ($_.CommandLine -match 'artisan\s+queue:work' -or $_.CommandLine -match 'artisan\s+schedule:work')
    }

foreach ($workerProcess in $workerProcesses) {
    Stop-Process -Id $workerProcess.ProcessId -Force -ErrorAction SilentlyContinue
    Write-Step "Worker PHP $($workerProcess.ProcessId) arrete."
}

Write-Step 'Workers arretes.'
