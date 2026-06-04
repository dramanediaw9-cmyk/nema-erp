param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [int]$QueueMaxTime = 3600
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

function Read-EnvValue($Path, $Key) {
    if (-not (Test-Path $Path)) {
        return $null
    }

    $line = Get-Content -Path $Path | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) {
        return $null
    }

    return ($line -split '=', 2)[1].Trim('"')
}

function Test-RedisBackplaneEnabled($RootPath) {
    $envPath = Join-Path $RootPath '.env'

    return @(
        Read-EnvValue -Path $envPath -Key 'CACHE_STORE'
        Read-EnvValue -Path $envPath -Key 'QUEUE_CONNECTION'
        Read-EnvValue -Path $envPath -Key 'SESSION_DRIVER'
    ) -contains 'redis'
}

function Start-NemaProcess($Name, $ArtisanArgs) {
    $runtimeRoot = Join-Path $ProjectRoot 'storage\app\runtime'
    $logRoot = Join-Path $ProjectRoot 'storage\logs'
    New-Item -ItemType Directory -Force -Path $runtimeRoot | Out-Null
    New-Item -ItemType Directory -Force -Path $logRoot | Out-Null

    $pidPath = Join-Path $runtimeRoot "$Name.pid"
    if (Test-Path $pidPath) {
        $existingPid = (Get-Content $pidPath -ErrorAction SilentlyContinue | Select-Object -First 1)
        if ($existingPid) {
            $existingProcess = Get-Process -Id ([int]$existingPid) -ErrorAction SilentlyContinue
            if ($existingProcess) {
                Write-Step "$Name est deja lance (PID $existingPid)."
                return
            }
        }
    }

    $stdoutPath = Join-Path $logRoot "$Name.out.log"
    $stderrPath = Join-Path $logRoot "$Name.err.log"
    $command = "cd /d `"$ProjectRoot`" && `"$PhpPath`" artisan $ArtisanArgs"

    $process = Start-Process `
        -FilePath (Get-Command cmd.exe).Source `
        -ArgumentList '/c', $command `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -PassThru

    Set-Content -Path $pidPath -Value $process.Id
    Write-Step "$Name lance (PID $($process.Id))."
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP introuvable: $PhpPath"
}

Write-Host 'Demarrage workers Nema ERP'
Write-Host '--------------------------'

if (Test-RedisBackplaneEnabled -RootPath $ProjectRoot) {
    $redisScript = Join-Path $ProjectRoot 'scripts\start-nema-redis.ps1'
    & powershell -ExecutionPolicy Bypass -File $redisScript -ProjectRoot $ProjectRoot
}

Start-NemaProcess -Name 'nema-queue-worker' -ArtisanArgs "queue:work --tries=3 --timeout=120 --max-time=$QueueMaxTime"
Start-NemaProcess -Name 'nema-scheduler-worker' -ArtisanArgs 'schedule:work'

Write-Step 'Workers demarres. Consulte storage\logs pour les sorties.'
