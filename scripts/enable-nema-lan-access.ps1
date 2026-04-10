param(
    [int]$Port = 8000,
    [string]$RuleName = 'Nema ERP Laravel 8000 (LocalSubnet)'
)

$ErrorActionPreference = 'Stop'

function Write-Step($Message) {
    Write-Host "[INFO] $Message"
}

function Test-IsAdministrator() {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-PrimaryIPv4() {
    return Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -notlike '127.*' -and
            $_.IPAddress -notlike '169.254*' -and
            $_.InterfaceAlias -notmatch 'Loopback'
        } |
        Sort-Object SkipAsSource, InterfaceMetric |
        Select-Object -First 1 -ExpandProperty IPAddress
}

if (-not (Test-IsAdministrator)) {
    throw 'Ce script doit etre lance dans PowerShell en mode administrateur.'
}

$existingRule = Get-NetFirewallRule -DisplayName $RuleName -ErrorAction SilentlyContinue
if (-not $existingRule) {
    New-NetFirewallRule `
        -DisplayName $RuleName `
        -Direction Inbound `
        -Profile Any `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $Port `
        -RemoteAddress LocalSubnet | Out-Null

    Write-Step "Regle pare-feu creee pour TCP $Port depuis le reseau local."
} else {
    Write-Step "La regle pare-feu existe deja pour TCP $Port."
}

$profiles = Get-NetConnectionProfile | Select-Object Name, InterfaceAlias, NetworkCategory
$address = Get-PrimaryIPv4

Write-Step 'Profils reseau actifs :'
$profiles | Format-Table -AutoSize

if ($address) {
    Write-Step "Application accessible sur http://${address}:$Port"
}
