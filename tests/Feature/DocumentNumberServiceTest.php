<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Services\PosBackofficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_product_sku_sequence_is_created_on_demand_and_skips_existing_codes(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $existingSkus = Product::query()
            ->where('company_id', $user->company_id)
            ->pluck('sku')
            ->all();

        $this->assertDatabaseMissing('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'product_sku',
        ]);

        $sku = app(DocumentNumberService::class)->nextNumber($user->company_id, 'product_sku');

        $this->assertMatchesRegularExpression('/^PRD-\d{4}$/', $sku);
        $this->assertNotContains($sku, $existingSkus);
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'product_sku',
            'prefix' => 'PRD-',
            'padding' => 4,
        ]);
    }

    public function test_partner_and_payment_term_sequences_are_created_on_demand(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $existingPartnerCodes = Partner::query()
            ->where('company_id', $user->company_id)
            ->pluck('code')
            ->all();

        $partnerCode = app(DocumentNumberService::class)->nextNumber($user->company_id, 'partner_customer_code');
        $paymentTermCode = app(DocumentNumberService::class)->nextNumber($user->company_id, 'payment_term_code');

        $this->assertMatchesRegularExpression('/^C\d{4}$/', $partnerCode);
        $this->assertNotContains($partnerCode, $existingPartnerCodes);
        $this->assertSame('TERM-001', $paymentTermCode);
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'partner_customer_code',
            'prefix' => 'C',
            'padding' => 4,
        ]);
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'payment_term_code',
            'prefix' => 'TERM-',
            'padding' => 3,
        ]);
    }

    public function test_pos_backoffice_uses_distinct_sequences_for_shared_card_table(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $service = app(PosBackofficeService::class);

        $giftCardCode = $service->nextCode('pos_stored_value_cards', 'GFT', $user->company_id);
        $walletCardCode = $service->nextCode('pos_stored_value_cards', 'WLT', $user->company_id);
        $nextGiftCardCode = $service->nextCode('pos_stored_value_cards', 'GFT', $user->company_id);

        $this->assertMatchesRegularExpression('/^GFT-\d{4}$/', $giftCardCode);
        $this->assertMatchesRegularExpression('/^WLT-\d{4}$/', $walletCardCode);
        $this->assertMatchesRegularExpression('/^GFT-\d{4}$/', $nextGiftCardCode);
        $this->assertNotSame($giftCardCode, $walletCardCode);
        $this->assertNotSame($giftCardCode, $nextGiftCardCode);
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'pos_gift_card_code',
            'prefix' => 'GFT-',
            'padding' => 4,
        ]);
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'document_type' => 'pos_wallet_card_code',
            'prefix' => 'WLT-',
            'padding' => 4,
        ]);
    }
}
