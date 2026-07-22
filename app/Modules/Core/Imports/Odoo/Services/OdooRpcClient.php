<?php

namespace App\Modules\Core\Imports\Odoo\Services;

use App\Modules\Core\Imports\Odoo\Contracts\OdooClient;
use App\Modules\Core\Imports\Odoo\Models\OdooConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OdooRpcClient implements OdooClient
{
    private ?int $uid = null;

    private array $fieldCache = [];

    public function __construct(
        private readonly OdooConnection $connection,
        private readonly OdooXmlRpcCodec $xmlCodec = new OdooXmlRpcCodec,
    ) {}

    public function authenticate(): int
    {
        if ($this->uid) {
            return $this->uid;
        }

        $uid = $this->connection->protocol === 'xmlrpc'
            ? $this->xmlRequest('/xmlrpc/2/common', 'authenticate', [
                $this->connection->database,
                $this->connection->username,
                $this->connection->secret,
                [],
            ])
            : $this->jsonRequest('common', 'login', [
                $this->connection->database,
                $this->connection->username,
                $this->connection->secret,
            ]);

        if (! is_int($uid) || $uid <= 0) {
            throw new RuntimeException('Authentification Odoo refusee. Verifiez la base, l utilisateur et le mot de passe ou la cle API.');
        }

        return $this->uid = $uid;
    }

    public function version(): array
    {
        $version = $this->connection->protocol === 'xmlrpc'
            ? $this->xmlRequest('/xmlrpc/2/common', 'version', [])
            : $this->jsonRequest('common', 'version', []);

        return is_array($version) ? $version : [];
    }

    public function supportedFields(string $model, array $fields): array
    {
        if (! array_key_exists($model, $this->fieldCache)) {
            $metadata = $this->executeKw($model, 'fields_get', [[], ['attributes' => ['string', 'type']]]);
            $this->fieldCache[$model] = is_array($metadata) ? array_keys($metadata) : [];
        }

        return array_values(array_intersect($fields, $this->fieldCache[$model]));
    }

    public function searchCount(string $model, array $domain): int
    {
        return (int) $this->executeKw($model, 'search_count', [[$domain], [
            'context' => ['active_test' => false],
        ]]);
    }

    public function searchRead(string $model, array $domain, array $fields, int $limit = 0, int $offset = 0, string $order = 'id asc'): array
    {
        $records = $this->executeKw($model, 'search_read', [[$domain], [
            'fields' => $this->supportedFields($model, $fields),
            'limit' => $limit,
            'offset' => $offset,
            'order' => $order,
            'context' => ['active_test' => false],
        ]]);

        return is_array($records) ? array_values($records) : [];
    }

    public function read(string $model, array $ids, array $fields): array
    {
        if ($ids === []) {
            return [];
        }

        $records = $this->executeKw($model, 'read', [[$ids], [
            'fields' => $this->supportedFields($model, $fields),
            'context' => ['active_test' => false],
        ]]);

        return is_array($records) ? array_values($records) : [];
    }

    private function executeKw(string $model, string $method, array $args): mixed
    {
        $parameters = [
            $this->connection->database,
            $this->authenticate(),
            $this->connection->secret,
            $model,
            $method,
            ...$args,
        ];

        return $this->connection->protocol === 'xmlrpc'
            ? $this->xmlRequest('/xmlrpc/2/object', 'execute_kw', $parameters)
            : $this->jsonRequest('object', 'execute_kw', $parameters);
    }

    private function jsonRequest(string $service, string $method, array $args): mixed
    {
        $response = $this->http()->post($this->endpoint('/jsonrpc'), [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => compact('service', 'method', 'args'),
            'id' => bin2hex(random_bytes(8)),
        ]);

        $response->throw();
        $payload = $response->json();
        if (isset($payload['error'])) {
            $message = data_get($payload, 'error.data.message') ?: data_get($payload, 'error.message') ?: 'Erreur JSON-RPC inconnue.';
            throw new RuntimeException('Odoo JSON-RPC: '.$message);
        }

        return $payload['result'] ?? null;
    }

    private function xmlRequest(string $path, string $method, array $params): mixed
    {
        $response = $this->http()
            ->withBody($this->xmlCodec->encode($method, $params), 'text/xml')
            ->post($this->endpoint($path));

        $response->throw();

        return $this->xmlCodec->decode($response->body());
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('odoo.timeout', 45))
            ->connectTimeout((int) config('odoo.connect_timeout', 10))
            ->withOptions(['verify' => (bool) $this->connection->verify_ssl]);
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->connection->url, '/').$path;
    }
}
