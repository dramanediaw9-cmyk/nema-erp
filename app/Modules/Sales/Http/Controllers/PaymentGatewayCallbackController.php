<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Treasury\Services\PaymentGatewayCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentGatewayCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayCallbackService $paymentGatewayCallbackService,
    ) {
    }

    public function store(Request $request, Company $company, string $method): JsonResponse
    {
        $callback = $this->paymentGatewayCallbackService->handle($company, $method, $request->all(), $request);
        $statusCode = $callback->processing_status === 'auto_recorded' ? 200 : 202;

        return response()->json([
            'ok' => true,
            'callback_id' => $callback->id,
            'invoice_id' => $callback->sales_invoice_id,
            'gateway_status' => $callback->gateway_status,
            'processing_status' => $callback->processing_status,
            'processing_status_label' => $callback->processingStatusLabel(),
            'payment_id' => $callback->payment_id,
        ], $statusCode);
    }
}

