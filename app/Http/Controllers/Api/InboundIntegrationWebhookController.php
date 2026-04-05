<?php

namespace App\Http\Controllers\Api;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Services\IntegrationInboundWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundIntegrationWebhookController
{
    public function __construct(private readonly IntegrationInboundWebhookService $integrationInboundWebhookService)
    {
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $result = $this->integrationInboundWebhookService->receive($company, $request);
        $receipt = $result['receipt'];

        return response()->json([
            'message' => $result['duplicate'] ? 'Webhook deja recu.' : 'Webhook recu et journalise.',
            'receipt_id' => $receipt->id,
            'status' => $receipt->status,
            'integration_event_id' => $receipt->integration_event_id,
        ], $result['duplicate'] ? 200 : 202);
    }
}
