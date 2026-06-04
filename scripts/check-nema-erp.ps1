param(
    [switch]$RequireProductionSettings
)

$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'
$buildManifest = Join-Path $projectRoot 'public\build\manifest.json'
$hotFile = Join-Path $projectRoot 'public\hot'
$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
$storagePath = Join-Path $projectRoot 'storage'
$cachePath = Join-Path $projectRoot 'bootstrap\cache'

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

Write-Host 'Verification environnement Nema ERP'
Write-Host '-----------------------------------'

if (Test-Path $envPath) {
    Write-Check 'OK' '.env present'
} else {
    Write-Check 'ERROR' '.env manquant'
}

if (Test-Path $vendorAutoload) {
    Write-Check 'OK' 'Dependances Composer presentes'
} else {
    Write-Check 'ERROR' 'vendor/autoload.php manquant. Lance composer install.'
}

if (Test-Path $storagePath) {
    Write-Check 'OK' 'Dossier storage present'
} else {
    Write-Check 'ERROR' 'Dossier storage manquant'
}

if (Test-Path $cachePath) {
    Write-Check 'OK' 'Dossier bootstrap/cache present'
} else {
    Write-Check 'ERROR' 'Dossier bootstrap/cache manquant'
}

if ((Test-Path $buildManifest) -or (Test-Path $hotFile)) {
    Write-Check 'OK' 'Assets front detectes'
} else {
    Write-Check 'WARN' 'Aucun build front detecte. Lance npm run build ou npm run dev.'
}

$appKey = Read-EnvValue -Path $envPath -Key 'APP_KEY'
if ($appKey) {
    Write-Check 'OK' 'APP_KEY renseignee'
} else {
    Write-Check 'ERROR' 'APP_KEY absente. Lance php artisan key:generate.'
}

$appDebug = Read-EnvValue -Path $envPath -Key 'APP_DEBUG'
if ($RequireProductionSettings -and $appDebug -ne 'false') {
    Write-Check 'ERROR' 'APP_DEBUG doit etre a false pour la mise en service.'
} elseif ($appDebug -eq 'false') {
    Write-Check 'OK' 'APP_DEBUG desactive'
} else {
    Write-Check 'WARN' 'APP_DEBUG actif ou non defini.'
}

$dbName = Read-EnvValue -Path $envPath -Key 'DB_DATABASE'
$dbUser = Read-EnvValue -Path $envPath -Key 'DB_USERNAME'
$dbPassword = Read-EnvValue -Path $envPath -Key 'DB_PASSWORD'
if ($dbName -and $dbUser) {
    Write-Check 'OK' "Configuration base detectee ($dbName / $dbUser)"
} else {
    Write-Check 'ERROR' 'Configuration base incomplete dans .env'
}

if ($RequireProductionSettings) {
    if ($dbUser -eq 'root') {
        Write-Check 'ERROR' 'DB_USERNAME ne doit pas etre root pour une mise en service.'
    } elseif ($dbUser) {
        Write-Check 'OK' 'Utilisateur base applicatif dedie'
    }

    if ([string]::IsNullOrWhiteSpace($dbPassword)) {
        Write-Check 'ERROR' 'DB_PASSWORD doit etre renseigne pour une mise en service.'
    } else {
        Write-Check 'OK' 'Mot de passe base renseigne'
    }
}

$mailMailer = Read-EnvValue -Path $envPath -Key 'MAIL_MAILER'
if ($RequireProductionSettings -and $mailMailer -eq 'log') {
    Write-Check 'WARN' 'MAIL_MAILER=log : les emails ne seront pas envoyes tant qu un SMTP reel n est pas configure.'
} elseif ($mailMailer) {
    Write-Check 'OK' "Mailer detecte ($mailMailer)"
}

Write-Host '-----------------------------------'
Write-Host 'Controle termine.'
