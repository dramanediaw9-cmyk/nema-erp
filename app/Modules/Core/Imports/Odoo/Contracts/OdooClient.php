<?php

namespace App\Modules\Core\Imports\Odoo\Contracts;

interface OdooClient
{
    public function authenticate(): int;

    public function version(): array;

    public function supportedFields(string $model, array $fields): array;

    public function searchCount(string $model, array $domain): int;

    public function searchRead(string $model, array $domain, array $fields, int $limit = 0, int $offset = 0, string $order = 'id asc'): array;

    public function read(string $model, array $ids, array $fields): array;
}
