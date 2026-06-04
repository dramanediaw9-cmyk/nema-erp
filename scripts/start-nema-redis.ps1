param(
    [string]$ProjectRoot = '',
    [string]$RedisServerPath = '',
    [int]$Port = 6379
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

function Wait-TcpPort($PortNumber, $TimeoutSeconds = 10) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    do {
        $ready = Test-NetConnection -ComputerName 127.0.0.1 -Port $PortNumber -WarningAction SilentlyContinue -InformationLevel Quiet
        if ($ready) {
            return $true
        }

        Start-Sleep -Milliseconds 400
    } while ((Get-Date) -lt $deadline)

    return $false
}

if (Wait-TcpPort -PortNumber $Port -TimeoutSeconds 1) {
    Write-Step "Redis est deja disponible sur le port $Port."
    return
}

if ([string]::IsNullOrWhiteSpace($RedisServerPath)) {
    $RedisServerPath = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter redis-server.exe -ErrorAction SilentlyContinue |
        Select-Object -First 1 -ExpandProperty FullName
}

if ([string]::IsNullOrWhiteSpace($RedisServerPath) -or -not (Test-Path $RedisServerPath)) {
    throw "redis-server introuvable: $RedisServerPath"
}

$runtimeRoot = Join-Path $ProjectRoot 'storage\app\runtime'
$redisRoot = Join-Path $ProjectRoot 'storage\app\redis'
$logRoot = Join-Path $ProjectRoot 'storage\logs'
New-Item -ItemType Directory -Force -Path $runtimeRoot | Out-Null
New-Item -ItemType Directory -Force -Path $redisRoot | Out-Null
New-Item -ItemType Directory -Force -Path $logRoot | Out-Null

$stdoutPath = Join-Path $logRoot 'nema-redis.out.log'
$stderrPath = Join-Path $logRoot 'nema-redis.err.log'
$pidPath = Join-Path $runtimeRoot 'nema-redis.pid'
$configPath = Join-Path $redisRoot 'redis.conf'

$redisRootForConfig = $redisRoot.Replace('\', '/')
$config = @"
bind 127.0.0.1
port $Port
dir "$redisRootForConfig"
appendonly yes
save 900 1
save 300 10
loglevel notice
"@

Set-Content -Path $configPath -Value $config -Encoding ASCII

$process = Start-Process `
    -FilePath $RedisServerPath `
    -ArgumentList 'redis.conf' `
    -WorkingDirectory $redisRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput $stdoutPath `
    -RedirectStandardError $stderrPath `
    -PassThru

Set-Content -Path $pidPath -Value $process.Id

if (-not (Wait-TcpPort -PortNumber $Port -TimeoutSeconds 10)) {
    throw "Redis ne repond pas sur le port $Port. Consulte $stderrPath"
}

Write-Step "Redis lance sur 127.0.0.1:$Port (PID $($process.Id))."
