param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

$envPath = Join-Path $ProjectRoot '.env'

function Write-Check($Status, $Message) {
    $prefix = switch ($Status) {
        'OK' { '[OK]' }
        'WARN' { '[ATTENTION]' }
        default { '[ERREUR]' }
    }

    Write-Host "$prefix $Message"
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

function Test-Tcp($HostName, $Port) {
    try {
        return Test-NetConnection -ComputerName $HostName -Port ([int]$Port) -WarningAction SilentlyContinue -InformationLevel Quiet
    } catch {
        return $false
    }
}

Write-Host 'Verification services Nema ERP'
Write-Host '------------------------------'

$cacheStore = Read-EnvValue -Path $envPath -Key 'CACHE_STORE'
$queueConnection = Read-EnvValue -Path $envPath -Key 'QUEUE_CONNECTION'
$sessionDriver = Read-EnvValue -Path $envPath -Key 'SESSION_DRIVER'
$redisHost = Read-EnvValue -Path $envPath -Key 'REDIS_HOST'
$redisPort = Read-EnvValue -Path $envPath -Key 'REDIS_PORT'
$redisClient = Read-EnvValue -Path $envPath -Key 'REDIS_CLIENT'

if ($cacheStore -eq 'redis' -or $queueConnection -eq 'redis' -or $sessionDriver -eq 'redis') {
    if ([string]::IsNullOrWhiteSpace($redisHost)) {
        $redisHost = '127.0.0.1'
    }

    if ([string]::IsNullOrWhiteSpace($redisPort)) {
        $redisPort = 6379
    }

    if ($redisClient -eq 'predis') {
        $predisPath = Join-Path $ProjectRoot 'vendor\predis\predis'
        if (Test-Path $predisPath) {
            Write-Check 'OK' 'Client Redis predis disponible'
        } else {
            Write-Check 'ERROR' 'REDIS_CLIENT=predis mais vendor\predis\predis est absent'
        }
    } else {
        $phpModules = & $PhpPath -m
        if ($phpModules -contains 'redis') {
            Write-Check 'OK' 'Extension PHP redis disponible'
        } else {
            Write-Check 'ERROR' 'Extension PHP redis absente'
        }
    }

    if (Test-Tcp -HostName $redisHost -Port $redisPort) {
        Write-Check 'OK' "Redis joignable sur $redisHost`:$redisPort"
    } else {
        Write-Check 'ERROR' "Redis non joignable sur $redisHost`:$redisPort"
    }
} else {
    Write-Check 'WARN' 'Redis non active: cache/session/queue restent sur database'
}

$mailMailer = Read-EnvValue -Path $envPath -Key 'MAIL_MAILER'
if ($mailMailer -eq 'smtp') {
    $mailHost = Read-EnvValue -Path $envPath -Key 'MAIL_HOST'
    $mailPort = Read-EnvValue -Path $envPath -Key 'MAIL_PORT'
    $mailUser = Read-EnvValue -Path $envPath -Key 'MAIL_USERNAME'
    $mailPassword = Read-EnvValue -Path $envPath -Key 'MAIL_PASSWORD'

    if ($mailHost -and $mailPort -and (Test-Tcp -HostName $mailHost -Port $mailPort)) {
        Write-Check 'OK' "SMTP joignable sur $mailHost`:$mailPort"
    } else {
        Write-Check 'ERROR' 'SMTP non joignable ou incomplet'
    }

    if ($mailUser -and $mailPassword) {
        Write-Check 'OK' 'Identifiants SMTP renseignes'
    } else {
        Write-Check 'WARN' 'Identifiants SMTP incomplets'
    }
} elseif ($mailMailer -eq 'log') {
    Write-Check 'WARN' 'MAIL_MAILER=log: aucun email reel ne partira'
} else {
    Write-Check 'OK' "Mailer configure: $mailMailer"
}

$filesystemDisk = Read-EnvValue -Path $envPath -Key 'FILESYSTEM_DISK'
$offsiteDisk = Read-EnvValue -Path $envPath -Key 'OPS_BACKUP_OFFSITE_DISK'
if ($filesystemDisk -eq 's3' -or $offsiteDisk -eq 's3') {
    $awsKey = Read-EnvValue -Path $envPath -Key 'AWS_ACCESS_KEY_ID'
    $awsSecret = Read-EnvValue -Path $envPath -Key 'AWS_SECRET_ACCESS_KEY'
    $awsBucket = Read-EnvValue -Path $envPath -Key 'AWS_BUCKET'

    if ($awsKey -and $awsSecret -and $awsBucket) {
        Write-Check 'OK' 'Configuration S3 renseignee'
    } else {
        Write-Check 'ERROR' 'Configuration S3 incomplete'
    }
} elseif ($offsiteDisk -eq 'offsite') {
    $offsitePath = Read-EnvValue -Path $envPath -Key 'OPS_BACKUP_OFFSITE_PATH'
    if ($offsitePath) {
        Write-Check 'OK' "Backup offsite local configure: $offsitePath"
    } else {
        Write-Check 'WARN' 'Backup offsite local configure sans OPS_BACKUP_OFFSITE_PATH'
    }
} else {
    Write-Check 'WARN' 'S3 non active: fichiers et backups restent locaux'
}

$workerProcesses = Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -eq 'php.exe' -and
        ($_.CommandLine -match 'artisan\s+queue:work' -or $_.CommandLine -match 'artisan\s+schedule:work')
    }

if (($workerProcesses | Measure-Object).Count -ge 2) {
    Write-Check 'OK' 'Queue worker et scheduler actifs'
} else {
    Write-Check 'WARN' 'Workers Laravel incomplets ou arretes'
}

Write-Host '------------------------------'
Write-Host 'Controle termine.'
