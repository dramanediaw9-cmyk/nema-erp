<?php

namespace Tests\Unit;

use App\Modules\Core\Imports\Odoo\Models\OdooConnection;
use App\Modules\Core\Imports\Odoo\Services\OdooRpcClient;
use App\Modules\Core\Imports\Odoo\Services\OdooXmlRpcCodec;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdooRpcClientTest extends TestCase
{
    public function test_json_rpc_search_read_uses_execute_kw_and_includes_inactive_records(): void
    {
        Http::fakeSequence()
            ->push(['jsonrpc' => '2.0', 'id' => '1', 'result' => 7])
            ->push(['jsonrpc' => '2.0', 'id' => '2', 'result' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'char']]])
            ->push(['jsonrpc' => '2.0', 'id' => '3', 'result' => [['id' => 10, 'name' => 'Produit']]]);

        $connection = new OdooConnection([
            'protocol' => 'jsonrpc',
            'url' => 'https://odoo.example.test',
            'database' => 'demo',
            'username' => 'api@example.test',
            'secret' => 'api-key',
            'verify_ssl' => true,
        ]);
        $records = (new OdooRpcClient($connection))->searchRead('product.template', [['id', '>', 0]], ['id', 'name', 'missing'], 100);

        $this->assertSame([['id' => 10, 'name' => 'Produit']], $records);
        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $args = $body['params']['args'] ?? [];

            return ($args[3] ?? null) === 'product.template'
                && ($args[4] ?? null) === 'search_read'
                && ($args[6]['fields'] ?? null) === ['id', 'name']
                && ($args[6]['context']['active_test'] ?? null) === false;
        });
    }

    public function test_xml_rpc_codec_round_trips_structures_and_faults(): void
    {
        $codec = new OdooXmlRpcCodec;
        $encoded = $codec->encode('execute_kw', ['demo', 7, ['active_test' => false], [10, 11]]);

        $this->assertStringContainsString('<methodName>execute_kw</methodName>', $encoded);
        $this->assertStringContainsString('<boolean>0</boolean>', $encoded);
        $this->assertStringContainsString('<array><data>', $encoded);

        $decoded = $codec->decode('<?xml version="1.0"?><methodResponse><params><param><value><struct><member><name>server_version</name><value><string>18.0</string></value></member><member><name>active</name><value><boolean>1</boolean></value></member></struct></value></param></params></methodResponse>');
        $this->assertSame(['server_version' => '18.0', 'active' => true], $decoded);
    }
}
