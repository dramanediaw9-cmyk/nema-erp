param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$XamppRoot = 'C:\xampp',
    [int]$Port = 8000,
    [string]$OpenPath = '/login',
    [switch]$SkipBrowser
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
}

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

function Wait-TcpPort($PortNumber, $TimeoutSeconds = 20) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    do {
        $ready = Test-NetConnection -ComputerName 127.0.0.1 -Port $PortNumber -WarningAction SilentlyContinue -InformationLevel Quiet
        if ($ready) {
            return $true
        }

        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)

    return $false
}

function Wait-HttpEndpoint($Url, $TimeoutSeconds = 30) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    do {
        try {
            $response = Invoke-WebRequest -Uri $Url -MaximumRedirection 0 -ErrorAction Stop
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
                return $true
            }
        } catch {
            if ($_.Exception.Response) {
                $statusCode = [int]$_.Exception.Response.StatusCode.value__
                if ($statusCode -ge 200 -and $statusCode -lt 500) {
                    return $true
                }
            }
        }

        Start-Sleep -Milliseconds 700
    } while ((Get-Date) -lt $deadline)

    return $false
}

function Start-XamppMysql($RootPath) {
    $mysqlStart = Join-Path $RootPath 'mysql_start.bat'

    if (-not (Test-Path $mysqlStart)) {
        throw "Script MySQL introuvable: $mysqlStart"
    }

    if (Wait-TcpPort -PortNumber 3306 -TimeoutSeconds 1) {
        Write-Step 'MariaDB XAMPP est deja disponible sur le port 3306.'
        return
    }

    Write-Step 'Demarrage de MariaDB XAMPP sur le port 3306...'
    Start-Process -FilePath (Get-Command cmd.exe).Source -ArgumentList '/c', "`"$mysqlStart`"" -WorkingDirectory $RootPath -WindowStyle Hidden | Out-Null

    if (-not (Wait-TcpPort -PortNumber 3306 -TimeoutSeconds 25)) {
        throw 'MariaDB XAMPP n a pas demarre sur le port 3306.'
    }

    Write-Step 'MariaDB XAMPP est pret.'
}

function Start-LaravelServer($RootPath, $PhpExecutable, $PortNumber) {
    if (-not (Test-Path $PhpExecutable)) {
        throw "PHP introuvable: $PhpExecutable"
    }

    if (Wait-TcpPort -PortNumber $PortNumber -TimeoutSeconds 1) {
        Write-Step "Le serveur Laravel est deja accessible sur le port $PortNumber."
        return
    }

    $logRoot = Join-Path $RootPath 'storage\logs'
    New-Item -ItemType Directory -Force -Path $logRoot | Out-Null
    $stdoutPath = Join-Path $logRoot "artisan-serve-$PortNumber.out.log"
    $stderrPath = Join-Path $logRoot "artisan-serve-$PortNumber.err.log"

    Write-Step "Demarrage de Laravel sur http://localhost:$PortNumber ..."
    $command = "set APP_URL=http://localhost:$PortNumber&& cd /d `"$RootPath`" && `"$PhpExecutable`" artisan serve --host=localhost --port=$PortNumber"
    Start-Process -FilePath (Get-Command cmd.exe).Source -ArgumentList '/c', $command -WorkingDirectory $RootPath -WindowStyle Hidden -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath | Out-Null

    $healthUrl = "http://localhost:$PortNumber/login"
    if (-not (Wait-HttpEndpoint -Url $healthUrl -TimeoutSeconds 35)) {
        throw "Laravel ne repond pas encore sur $healthUrl. Consulte $stderrPath"
    }

    Write-Step "Laravel repond sur http://localhost:$PortNumber."
}

$openPathNormalized = if ([string]::IsNullOrWhiteSpace($OpenPath)) { '/login' } elseif ($OpenPath.StartsWith('/')) { $OpenPath } else { "/$OpenPath" }
$targetUrl = "http://localhost:$Port$openPathNormalized"

Write-Host 'Demarrage local Nema ERP'
Write-Host '------------------------'

Start-XamppMysql -RootPath $XamppRoot
Start-LaravelServer -RootPath $ProjectRoot -PhpExecutable $PhpPath -PortNumber $Port

Write-Step "Application disponible sur $targetUrl"

if (-not $SkipBrowser) {
    Start-Process $targetUrl
    Write-Step 'Le navigateur a ete ouvert.'
}
