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

$pidPath = Join-Path $ProjectRoot 'storage\app\runtime\nema-redis.pid'

if (Test-Path $pidPath) {
    $pidValue = Get-Content $pidPath -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($pidValue) {
        $process = Get-Process -Id ([int]$pidValue) -ErrorAction SilentlyContinue
        if ($process) {
            Stop-Process -Id $process.Id -Force
            Write-Step "Redis arrete (PID $($process.Id))."
        }
    }

    Remove-Item -LiteralPath $pidPath -Force
}

$redisProcesses = Get-CimInstance Win32_Process |
    Where-Object { $_.Name -eq 'redis-server.exe' -and $_.CommandLine -match '127\.0\.0\.1' }

foreach ($redisProcess in $redisProcesses) {
    Stop-Process -Id $redisProcess.ProcessId -Force -ErrorAction SilentlyContinue
    Write-Step "Processus Redis $($redisProcess.ProcessId) arrete."
}
