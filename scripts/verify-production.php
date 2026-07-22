<?php

declare(strict_types=1);

/**
 * Verification HTTP sans effet de bord pour Nema ERP.
 *
 * Usage :
 *   php scripts/verify-production.php
 *   php scripts/verify-production.php --base-url=https://erp.example.com --json
 */

$options = getopt('', ['base-url::', 'timeout::', 'json']);
$baseUrl = rtrim((string) ($options['base-url'] ?? 'https://erp.nematechnologies.com'), '/');
$timeout = max((int) ($options['timeout'] ?? 15), 3);
$json = array_key_exists('json', $options);

if (! extension_loaded('curl')) {
    fwrite(STDERR, "L extension PHP cURL est requise.\n");
    exit(2);
}

if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! str_starts_with($baseUrl, 'https://')) {
    fwrite(STDERR, "Une URL HTTPS valide est requise.\n");
    exit(2);
}

/** @return array{status:int,headers:array<string,string>,body:string,duration_ms:int,error:?string} */
function fetchUrl(string $url, int $timeout): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min($timeout, 8),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'Cache-Control: no-cache',
            'User-Agent: NemaERP-ProductionVerifier/1.0',
        ],
    ]);

    $startedAt = microtime(true);
    $raw = curl_exec($handle);
    $duration = (int) round((microtime(true) - $startedAt) * 1000);
    $error = $raw === false ? curl_error($handle) : null;
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    $headerText = $raw === false ? '' : substr($raw, 0, $headerSize);
    $body = $raw === false ? '' : substr($raw, $headerSize);
    $headers = [];

    foreach (preg_split('/\r\n|\r|\n/', trim($headerText)) ?: [] as $line) {
        if (! str_contains($line, ':')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode(':', $line, 2));
        $headers[strtolower($name)] = $value;
    }

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => $body,
        'duration_ms' => $duration,
        'error' => $error,
    ];
}

$responses = [
    'login' => fetchUrl($baseUrl.'/login', $timeout),
    'health' => fetchUrl($baseUrl.'/up', $timeout),
    'dashboard_guest' => fetchUrl($baseUrl.'/dashboard', $timeout),
    'not_found' => fetchUrl($baseUrl.'/codex-release-check-not-found', $timeout),
    'compact_css' => fetchUrl($baseUrl.'/css/erp-compact.css?v=20260721-7', $timeout),
    'form_safety_js' => fetchUrl($baseUrl.'/js/form-safety.js', $timeout),
];

$checks = [];
$addCheck = static function (string $name, bool $passed, string $expected, string $actual, int $duration = 0) use (&$checks): void {
    $checks[] = compact('name', 'passed', 'expected', 'actual', 'duration');
};

$addCheck('Connexion publique', $responses['login']['status'] === 200, 'HTTP 200', 'HTTP '.$responses['login']['status'], $responses['login']['duration_ms']);
$addCheck('Sante Laravel', $responses['health']['status'] === 200, 'HTTP 200', 'HTTP '.$responses['health']['status'], $responses['health']['duration_ms']);
$dashboardLocation = $responses['dashboard_guest']['headers']['location'] ?? '';
$addCheck(
    'Tableau de bord sans session',
    in_array($responses['dashboard_guest']['status'], [301, 302, 303, 307, 308], true)
        && str_contains($dashboardLocation, '/login'),
    'redirection vers la connexion',
    'HTTP '.$responses['dashboard_guest']['status'].' vers '.($dashboardLocation ?: 'destination absente'),
    $responses['dashboard_guest']['duration_ms'],
);
$addCheck('Page inexistante', $responses['not_found']['status'] === 404, 'HTTP 404', 'HTTP '.$responses['not_found']['status'], $responses['not_found']['duration_ms']);
$addCheck('CSS compact', $responses['compact_css']['status'] === 200 && str_contains($responses['compact_css']['body'], 'Matrice responsive'), 'HTTP 200 et marqueur responsive', 'HTTP '.$responses['compact_css']['status'], $responses['compact_css']['duration_ms']);
$addCheck('Securite formulaires JS', $responses['form_safety_js']['status'] === 200 && str_contains($responses['form_safety_js']['body'], 'is-submitting'), 'HTTP 200 et garde double envoi', 'HTTP '.$responses['form_safety_js']['status'], $responses['form_safety_js']['duration_ms']);

$loginHeaders = $responses['login']['headers'];
$requiredHeaders = [
    'strict-transport-security' => 'max-age=',
    'x-frame-options' => 'DENY',
    'x-content-type-options' => 'nosniff',
    'referrer-policy' => 'strict-origin-when-cross-origin',
    'permissions-policy' => 'camera=()',
    'cross-origin-opener-policy' => 'same-origin',
    'cross-origin-resource-policy' => 'same-origin',
    'content-security-policy' => 'upgrade-insecure-requests',
];

foreach ($requiredHeaders as $header => $needle) {
    $actual = $loginHeaders[$header] ?? '';
    $addCheck(
        'En-tete '.$header,
        $actual !== '' && str_contains(strtolower($actual), strtolower($needle)),
        'contient '.$needle,
        $actual === '' ? 'absent' : $actual,
    );
}

$loginBody = html_entity_decode($responses['login']['body'], ENT_QUOTES | ENT_HTML5);
$addCheck(
    'CSP HTML complete',
    str_contains($loginBody, 'http-equiv="Content-Security-Policy"')
        && str_contains($loginBody, "default-src 'self'")
        && str_contains($loginBody, "object-src 'none'"),
    'meta CSP avec default-src et object-src',
    str_contains($loginBody, 'http-equiv="Content-Security-Policy"') ? 'presente' : 'absente',
);

$failures = array_values(array_filter($checks, static fn (array $check): bool => ! $check['passed']));
$slow = array_values(array_filter($checks, static fn (array $check): bool => $check['duration'] > 5000));
$result = [
    'status' => $failures === [] ? 'ok' : 'fail',
    'base_url' => $baseUrl,
    'checked_at_utc' => gmdate(DATE_ATOM),
    'checks' => $checks,
    'failures' => count($failures),
    'slow_checks' => count($slow),
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
} else {
    foreach ($checks as $check) {
        $duration = $check['duration'] > 0 ? sprintf(' (%d ms)', $check['duration']) : '';
        printf("[%s] %s%s\n", $check['passed'] ? 'OK' : 'ECHEC', $check['name'], $duration);
        if (! $check['passed']) {
            printf("       attendu: %s | recu: %s\n", $check['expected'], $check['actual']);
        }
    }

    printf("\nResultat: %s — %d controle(s), %d echec(s), %d controle(s) lent(s).\n", strtoupper($result['status']), count($checks), count($failures), count($slow));
}

exit($failures === [] ? 0 : 1);
