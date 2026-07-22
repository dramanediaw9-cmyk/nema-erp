<?php

namespace App\Modules\Core\Imports\Services;

use App\Models\User;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Services\PurchaseBillService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Services\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ImportService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly PurchaseBillService $purchaseBillService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function importCustomers(UploadedFile $file, int $companyId, User $user): array
    {
        $rows = $this->parseTabular($file, $this->partnerImportHeaders(), allowExtraColumns: true);

        $imported = DB::transaction(function () use ($rows, $companyId) {
            $count = 0;

            foreach ($rows as $row) {
                $this->upsertPartner($row, $companyId, 'customer');
                $count++;
            }

            return $count;
        });

        return [
            'count' => $imported,
            'type' => 'customers',
            'user_id' => $user->id,
        ];
    }

    public function importSuppliers(UploadedFile $file, int $companyId, User $user): array
    {
        $rows = $this->parseTabular($file, $this->partnerImportHeaders(), allowExtraColumns: true);

        $imported = DB::transaction(function () use ($rows, $companyId) {
            $count = 0;

            foreach ($rows as $row) {
                $this->upsertPartner($row, $companyId, 'supplier');
                $count++;
            }

            return $count;
        });

        return [
            'count' => $imported,
            'type' => 'suppliers',
            'user_id' => $user->id,
        ];
    }

    public function importProducts(UploadedFile $file, int $companyId, int $branchId, User $user): array
    {
        $headers = [
            'sku',
            'barcode',
            'name',
            'category',
            'unit',
            'type',
            'sale_price',
            'purchase_price',
            'min_stock',
            'opening_quantity',
            'opening_unit_cost',
            'description',
        ];

        $legacyHeaders = [
            'sku',
            'name',
            'category',
            'unit',
            'type',
            'sale_price',
            'purchase_price',
            'min_stock',
            'description',
        ];

        $rows = $this->parseTabular($file, $headers, [$legacyHeaders]);

        [$imported, $stockLines] = DB::transaction(function () use ($rows, $companyId, $branchId, $user) {
            $count = 0;
            $stockCount = 0;

            foreach ($rows as $row) {
                $sku = $this->nullable($row['sku'] ?? null);
                $barcode = $this->nullable($row['barcode'] ?? null);
                $type = $this->nullable($row['type'] ?? null) ?: 'stockable';
                $existingProductId = $this->existingProductId($companyId, $sku, $barcode, $this->nullable($row['name'] ?? null));
                if (! $barcode && $sku) {
                    $barcode = $this->defaultBarcodeFromSku($companyId, $sku, $existingProductId);
                    $row['barcode'] = $barcode;
                }

                $validator = Validator::make($row, [
                    'sku' => [
                        'nullable',
                        'string',
                        'max:50',
                        Rule::unique('products', 'sku')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($existingProductId),
                    ],
                    'barcode' => [
                        'nullable',
                        'string',
                        'max:100',
                        Rule::unique('products', 'barcode')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($existingProductId),
                    ],
                    'name' => ['required', 'string', 'max:255'],
                    'category' => ['nullable', 'string', 'max:255'],
                    'unit' => ['required', 'string', 'max:50'],
                    'type' => ['nullable', Rule::in(['stockable', 'service'])],
                    'sale_price' => ['required', 'numeric', 'min:0'],
                    'purchase_price' => ['required', 'numeric', 'min:0'],
                    'min_stock' => ['nullable', 'numeric', 'min:0'],
                    'opening_quantity' => ['nullable', 'numeric', 'min:0'],
                    'opening_unit_cost' => ['nullable', 'numeric', 'min:0'],
                    'description' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$row['_line'].': '.$validator->errors()->first(),
                    ]);
                }

                $categoryName = $this->nullable($row['category'] ?? null);
                $category = null;

                if ($categoryName) {
                    $category = ProductCategory::query()->firstOrCreate(
                        ['company_id' => $companyId, 'name' => trim($categoryName)],
                        ['description' => null, 'is_active' => true]
                    );
                }

                $attributes = [
                    'company_id' => $companyId,
                    'category_id' => $category?->id,
                    'name' => trim((string) $row['name']),
                    'unit' => trim((string) $row['unit']),
                    'type' => $type,
                    'barcode' => $barcode,
                    'sale_ok' => true,
                    'purchase_ok' => true,
                    'sale_blocked' => false,
                    'purchase_blocked' => false,
                    'invoice_policy' => 'ordered',
                    'tracking_type' => 'none',
                    'sale_price' => (float) $row['sale_price'],
                    'purchase_price' => (float) $row['purchase_price'],
                    'min_stock' => (float) ($row['min_stock'] ?: 0),
                    'description' => $this->nullable($row['description'] ?? null),
                    'is_active' => true,
                ];

                $product = null;

                if ($existingProductId) {
                    $product = Product::query()->where('company_id', $companyId)->find($existingProductId);
                }

                if ($product) {
                    $resolvedSku = $sku ?: $product->sku;
                    $product->update(array_merge($attributes, [
                        'sku' => $resolvedSku,
                        'barcode' => $barcode ?: $this->defaultBarcodeFromSku($companyId, $resolvedSku, $product->id),
                    ]));
                } else {
                    $resolvedSku = $sku ?: $this->generateSku($companyId);
                    $product = Product::query()->create(array_merge($attributes, [
                        'sku' => $resolvedSku,
                        'barcode' => $barcode ?: $this->defaultBarcodeFromSku($companyId, $resolvedSku),
                    ]));
                }

                $openingQuantity = (float) ($row['opening_quantity'] ?? 0);
                if ($type === 'stockable' && $openingQuantity > 0) {
                    $this->stockService->recordOpening(
                        product: $product,
                        companyId: $companyId,
                        branchId: $branchId,
                        quantity: $openingQuantity,
                        unitCost: (float) (($row['opening_unit_cost'] ?? null) ?: $product->purchase_price),
                        notes: 'Import produits - stock initial',
                        user: $user,
                    );
                    $stockCount++;
                }

                $count++;
            }

            return [$count, $stockCount];
        });

        return [
            'count' => $imported,
            'stock_count' => $stockLines,
            'type' => 'products',
            'branch_id' => $branchId,
            'user_id' => $user->id,
        ];
    }

    public function importOpeningStock(UploadedFile $file, int $companyId, int $branchId, User $user): array
    {
        $rows = $this->parseCsv($file, [
            'sku',
            'quantity',
            'unit_cost',
            'notes',
        ]);

        $imported = DB::transaction(function () use ($rows, $companyId, $branchId, $user) {
            $count = 0;

            foreach ($rows as $row) {
                $validator = Validator::make($row, [
                    'sku' => ['required', 'string', 'max:50'],
                    'quantity' => ['required', 'numeric', 'gt:0'],
                    'unit_cost' => ['nullable', 'numeric', 'min:0'],
                    'notes' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$row['_line'].': '.$validator->errors()->first(),
                    ]);
                }

                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->where('type', 'stockable')
                    ->where('sku', trim((string) $row['sku']))
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$row['_line'].': produit introuvable pour le SKU '.trim((string) $row['sku']).'.',
                    ]);
                }

                $this->stockService->recordOpening(
                    product: $product,
                    companyId: $companyId,
                    branchId: $branchId,
                    quantity: (float) $row['quantity'],
                    unitCost: (float) ($row['unit_cost'] ?: $product->purchase_price),
                    notes: $this->nullable($row['notes'] ?? null),
                    user: $user,
                );

                $count++;
            }

            return $count;
        });

        return [
            'count' => $imported,
            'type' => 'opening_stock',
            'branch_id' => $branchId,
            'user_id' => $user->id,
        ];
    }

    public function importHistoricalSales(UploadedFile $file, int $companyId, int $branchId, User $user): array
    {
        $rows = $this->parseCsv($file, [
            'invoice_number',
            'invoice_date',
            'due_date',
            'customer_code',
            'sku',
            'description',
            'qty',
            'unit_price',
            'amount_paid',
            'payment_date',
            'cash_account',
            'notes',
        ]);

        $imported = DB::transaction(function () use ($rows, $companyId, $branchId, $user) {
            $count = 0;

            foreach (collect($rows)->groupBy(fn (array $row) => trim((string) $row['invoice_number'])) as $invoiceNumber => $group) {
                $invoiceNumber = trim((string) $invoiceNumber);

                if ($invoiceNumber === '') {
                    $line = $group->first()['_line'] ?? '?';

                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$line.': numero de facture historique obligatoire.',
                    ]);
                }

                if (SalesInvoice::query()->where('company_id', $companyId)->where('invoice_number', $invoiceNumber)->exists()) {
                    throw ValidationException::withMessages([
                        'import' => 'Le numero de facture '.$invoiceNumber.' existe deja dans cette societe.',
                    ]);
                }

                $document = $this->prepareHistoricalSalesDocument($group->values(), $companyId);

                $invoice = $this->salesInvoiceService->createHistorical(
                    $companyId,
                    $branchId,
                    $document['customer'],
                    $invoiceNumber,
                    [
                        'invoice_date' => $document['invoice_date'],
                        'due_date' => $document['due_date'],
                        'notes' => $document['notes'],
                        'validated_at' => $document['invoice_date'],
                    ],
                    $document['items'],
                    $user,
                );

                if ($document['amount_paid'] > 0) {
                    $cashAccount = $this->resolveCashAccount($companyId, $document['cash_account'], $document['line']);

                    $this->paymentService->recordCustomerReceipt(
                        $companyId,
                        $branchId,
                        $invoice,
                        $cashAccount,
                        [
                            'payment_date' => $document['payment_date'] ?: $document['invoice_date'],
                            'amount' => $document['amount_paid'],
                            'method' => 'other',
                            'reference' => 'IMPORT-'.$invoiceNumber,
                            'notes' => 'Import historique vente '.$invoiceNumber,
                        ],
                        $user,
                    );
                }

                $count++;
            }

            return $count;
        });

        return [
            'count' => $imported,
            'type' => 'historical_sales',
            'branch_id' => $branchId,
            'user_id' => $user->id,
        ];
    }

    public function importHistoricalPurchases(UploadedFile $file, int $companyId, int $branchId, User $user): array
    {
        $rows = $this->parseCsv($file, [
            'bill_number',
            'bill_date',
            'due_date',
            'supplier_code',
            'sku',
            'description',
            'qty',
            'unit_cost',
            'amount_paid',
            'payment_date',
            'cash_account',
            'notes',
        ]);

        $imported = DB::transaction(function () use ($rows, $companyId, $branchId, $user) {
            $count = 0;

            foreach (collect($rows)->groupBy(fn (array $row) => trim((string) $row['bill_number'])) as $billNumber => $group) {
                $billNumber = trim((string) $billNumber);

                if ($billNumber === '') {
                    $line = $group->first()['_line'] ?? '?';

                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$line.': numero de facture fournisseur historique obligatoire.',
                    ]);
                }

                if (PurchaseBill::query()->where('company_id', $companyId)->where('bill_number', $billNumber)->exists()) {
                    throw ValidationException::withMessages([
                        'import' => 'Le numero de facture fournisseur '.$billNumber.' existe deja dans cette societe.',
                    ]);
                }

                $document = $this->prepareHistoricalPurchaseDocument($group->values(), $companyId);

                $bill = $this->purchaseBillService->createHistorical(
                    $companyId,
                    $branchId,
                    $document['supplier'],
                    $billNumber,
                    [
                        'bill_date' => $document['bill_date'],
                        'due_date' => $document['due_date'],
                        'notes' => $document['notes'],
                        'validated_at' => $document['bill_date'],
                    ],
                    $document['items'],
                    $user,
                );

                if ($document['amount_paid'] > 0) {
                    $cashAccount = $this->resolveCashAccount($companyId, $document['cash_account'], $document['line']);

                    $this->paymentService->recordSupplierPayment(
                        $companyId,
                        $branchId,
                        $bill,
                        $cashAccount,
                        [
                            'payment_date' => $document['payment_date'] ?: $document['bill_date'],
                            'amount' => $document['amount_paid'],
                            'method' => 'other',
                            'reference' => 'IMPORT-'.$billNumber,
                            'notes' => 'Import historique achat '.$billNumber,
                        ],
                        $user,
                    );
                }

                $count++;
            }

            return $count;
        });

        return [
            'count' => $imported,
            'type' => 'historical_purchases',
            'branch_id' => $branchId,
            'user_id' => $user->id,
        ];
    }

    public function customerTemplate(): array
    {
        return [
            ['code', 'nom', 'telephone', 'email', 'ville', 'nif', 'adresse', 'solde_initial', 'condition_paiement', 'liste_prix', 'actif', 'notes'],
            ['CLI-1001', 'Boutique Bamako Centre', '70000000', 'client@example.com', 'Bamako', '', 'Hamdallaye ACI 2000', '0', '', '', 'oui', 'Client de demonstration'],
        ];
    }

    public function supplierTemplate(): array
    {
        return [
            ['code', 'nom', 'telephone', 'email', 'ville', 'nif', 'adresse', 'solde_initial', 'condition_paiement', 'liste_prix', 'actif', 'notes'],
            ['FOU-1001', 'Fournisseur Import Demo', '76000000', 'fournisseur@example.com', 'Bamako', '', 'Zone industrielle', '0', '', '', 'oui', 'Fournisseur de demonstration'],
        ];
    }

    public function productTemplate(): array
    {
        return [
            ['sku', 'barcode', 'name', 'category', 'unit', 'type', 'sale_price', 'purchase_price', 'min_stock', 'opening_quantity', 'opening_unit_cost', 'description'],
            ['PRD-1001', '6181000000012', 'Riz 25 kg', 'Produits alimentaires', 'sac', 'stockable', '17500', '15000', '10', '25', '15000', 'Article de demonstration'],
            ['PRD-1002', '6181000000029', 'Huile 5 L', 'Produits alimentaires', 'bidon', 'stockable', '8500', '7600', '6', '12', '7600', 'Article de demonstration'],
        ];
    }

    public function xlsxFromRows(array $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'file' => 'La generation Excel necessite ZipArchive sur le serveur.',
            ]);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'nema-xlsx-');
        $zip = new ZipArchive();
        $zip->open($temporary, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Produits" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheetXml($rows));
        $zip->close();

        $content = file_get_contents($temporary);
        @unlink($temporary);

        return $content ?: '';
    }

    public function openingStockTemplate(): array
    {
        return [
            ['sku', 'quantity', 'unit_cost', 'notes'],
            ['PRD-1001', '25', '15000', 'Stock initial de demonstration'],
        ];
    }

    public function historicalSalesTemplate(): array
    {
        return [
            ['invoice_number', 'invoice_date', 'due_date', 'customer_code', 'sku', 'description', 'qty', 'unit_price', 'amount_paid', 'payment_date', 'cash_account', 'notes'],
            ['HIS-VTE-0001', '2026-03-01', '2026-03-10', 'C0001', 'PRD-0001', 'Historique eau', '4', '400', '1000', '2026-03-02', 'Caisse principale', 'Migration vente historique'],
            ['HIS-VTE-0001', '2026-03-01', '2026-03-10', 'C0001', 'PRD-0002', 'Historique sucre', '2', '700', '1000', '2026-03-02', 'Caisse principale', 'Migration vente historique'],
        ];
    }

    public function historicalPurchasesTemplate(): array
    {
        return [
            ['bill_number', 'bill_date', 'due_date', 'supplier_code', 'sku', 'description', 'qty', 'unit_cost', 'amount_paid', 'payment_date', 'cash_account', 'notes'],
            ['HIS-ACH-0001', '2026-03-01', '2026-03-12', 'F0001', 'PRD-0001', 'Historique achat eau', '10', '250', '800', '2026-03-03', 'Banque BDM', 'Migration achat historique'],
            ['HIS-ACH-0001', '2026-03-01', '2026-03-12', 'F0001', 'PRD-0002', 'Historique achat sucre', '6', '500', '800', '2026-03-03', 'Banque BDM', 'Migration achat historique'],
        ];
    }

    private function prepareHistoricalSalesDocument(Collection $group, int $companyId): array
    {
        $first = $group->first();
        $invoiceNumber = trim((string) ($first['invoice_number'] ?? ''));

        $this->assertUniformDocumentFields($group, ['invoice_date', 'due_date', 'customer_code', 'amount_paid', 'payment_date', 'cash_account', 'notes'], $invoiceNumber, 'facture de vente');

        $customerCode = trim((string) ($first['customer_code'] ?? ''));
        $customer = Partner::query()->customers()->where('company_id', $companyId)->where('code', $customerCode)->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': client introuvable pour le code '.$customerCode.'.',
            ]);
        }

        $items = [];

        foreach ($group as $row) {
            $validator = Validator::make($row, [
                'invoice_number' => ['required', 'string', 'max:50'],
                'invoice_date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'customer_code' => ['required', 'string', 'max:50'],
                'sku' => ['required', 'string', 'max:50'],
                'description' => ['nullable', 'string', 'max:255'],
                'qty' => ['required', 'numeric', 'gt:0'],
                'unit_price' => ['required', 'numeric', 'min:0'],
                'amount_paid' => ['nullable', 'numeric', 'min:0'],
                'payment_date' => ['nullable', 'date'],
                'cash_account' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'import' => 'Ligne '.$row['_line'].': '.$validator->errors()->first(),
                ]);
            }

            $product = Product::query()->where('company_id', $companyId)->where('sku', trim((string) $row['sku']))->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'import' => 'Ligne '.$row['_line'].': produit introuvable pour le SKU '.trim((string) $row['sku']).'.',
                ]);
            }

            $items[] = [
                'product_id' => $product->id,
                'description' => $this->nullable($row['description'] ?? null) ?: $product->name,
                'qty' => (float) $row['qty'],
                'unit_price' => (float) $row['unit_price'],
            ];
        }

        $normalizedItems = $this->salesInvoiceService->normalizeItems($companyId, $items);
        $total = (float) $normalizedItems->sum('line_total');
        $amountPaid = round((float) ($first['amount_paid'] ?: 0), 2);
        $cashAccount = $this->nullable($first['cash_account'] ?? null);

        if ($amountPaid > $total) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': le montant deja paye depasse le total de la facture '.$invoiceNumber.'.',
            ]);
        }

        if ($amountPaid > 0 && ! $cashAccount) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': indique un compte de tresorerie si un montant deja paye est renseigne.',
            ]);
        }

        return [
            'line' => $first['_line'],
            'customer' => $customer,
            'invoice_date' => $first['invoice_date'],
            'due_date' => $this->nullable($first['due_date'] ?? null),
            'notes' => $this->nullable($first['notes'] ?? null),
            'items' => $normalizedItems,
            'amount_paid' => $amountPaid,
            'payment_date' => $this->nullable($first['payment_date'] ?? null),
            'cash_account' => $cashAccount,
        ];
    }

    private function prepareHistoricalPurchaseDocument(Collection $group, int $companyId): array
    {
        $first = $group->first();
        $billNumber = trim((string) ($first['bill_number'] ?? ''));

        $this->assertUniformDocumentFields($group, ['bill_date', 'due_date', 'supplier_code', 'amount_paid', 'payment_date', 'cash_account', 'notes'], $billNumber, 'facture fournisseur');

        $supplierCode = trim((string) ($first['supplier_code'] ?? ''));
        $supplier = Partner::query()->suppliers()->where('company_id', $companyId)->where('code', $supplierCode)->first();

        if (! $supplier) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': fournisseur introuvable pour le code '.$supplierCode.'.',
            ]);
        }

        $items = [];

        foreach ($group as $row) {
            $validator = Validator::make($row, [
                'bill_number' => ['required', 'string', 'max:50'],
                'bill_date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'supplier_code' => ['required', 'string', 'max:50'],
                'sku' => ['required', 'string', 'max:50'],
                'description' => ['nullable', 'string', 'max:255'],
                'qty' => ['required', 'numeric', 'gt:0'],
                'unit_cost' => ['required', 'numeric', 'min:0'],
                'amount_paid' => ['nullable', 'numeric', 'min:0'],
                'payment_date' => ['nullable', 'date'],
                'cash_account' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'import' => 'Ligne '.$row['_line'].': '.$validator->errors()->first(),
                ]);
            }

            $product = Product::query()->where('company_id', $companyId)->where('sku', trim((string) $row['sku']))->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'import' => 'Ligne '.$row['_line'].': produit introuvable pour le SKU '.trim((string) $row['sku']).'.',
                ]);
            }

            $items[] = [
                'product_id' => $product->id,
                'description' => $this->nullable($row['description'] ?? null) ?: $product->name,
                'qty' => (float) $row['qty'],
                'unit_cost' => (float) $row['unit_cost'],
            ];
        }

        $normalizedItems = $this->purchaseBillService->normalizeItems($companyId, $items);
        $total = (float) $normalizedItems->sum('line_total');
        $amountPaid = round((float) ($first['amount_paid'] ?: 0), 2);
        $cashAccount = $this->nullable($first['cash_account'] ?? null);

        if ($amountPaid > $total) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': le montant deja regle depasse le total de la facture fournisseur '.$billNumber.'.',
            ]);
        }

        if ($amountPaid > 0 && ! $cashAccount) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$first['_line'].': indique un compte de tresorerie si un montant deja regle est renseigne.',
            ]);
        }

        return [
            'line' => $first['_line'],
            'supplier' => $supplier,
            'bill_date' => $first['bill_date'],
            'due_date' => $this->nullable($first['due_date'] ?? null),
            'notes' => $this->nullable($first['notes'] ?? null),
            'items' => $normalizedItems,
            'amount_paid' => $amountPaid,
            'payment_date' => $this->nullable($first['payment_date'] ?? null),
            'cash_account' => $cashAccount,
        ];
    }

    private function assertUniformDocumentFields(Collection $group, array $keys, string $documentNumber, string $label): void
    {
        $first = $group->first();

        foreach ($keys as $key) {
            $expected = $this->nullable($first[$key] ?? null);

            foreach ($group as $row) {
                $value = $this->nullable($row[$key] ?? null);

                if ($value !== $expected) {
                    throw ValidationException::withMessages([
                        'import' => 'Ligne '.$row['_line'].': la colonne '.$key.' doit etre identique pour toutes les lignes de la '.$label.' '.$documentNumber.'.',
                    ]);
                }
            }
        }
    }

    private function resolveCashAccount(int $companyId, ?string $cashAccountName, int $line): CashAccount
    {
        $name = $this->nullable($cashAccountName);

        if (! $name) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$line.': compte de tresorerie obligatoire pour enregistrer un montant deja paye.',
            ]);
        }

        $cashAccount = CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('name', $name)
            ->first();

        if (! $cashAccount) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$line.': compte de tresorerie introuvable pour le nom '.$name.'.',
            ]);
        }

        return $cashAccount;
    }

    private function upsertPartner(array $row, int $companyId, string $type): void
    {
        $code = $this->nullable($row['code'] ?? null);
        $email = $this->nullable($row['email'] ?? null);
        $scope = $type === 'supplier' ? fn ($q) => $q->suppliers() : fn ($q) => $q->customers();

        $validator = Validator::make($row, [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('partners', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore(
                    $code ? Partner::query()->where('company_id', $companyId)->where('code', $code)->value('id') : null
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'payment_term' => ['nullable', 'string', 'max:255'],
            'price_list' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$row['_line'].': '.$validator->errors()->first(),
            ]);
        }

        $attributes = [
            'company_id' => $companyId,
            'type' => $type,
            'name' => trim((string) $row['name']),
            'phone' => $this->nullable($row['phone'] ?? null),
            'email' => $email,
            'city' => $this->nullable($row['city'] ?? null),
            'nif' => $this->nullable($row['nif'] ?? null),
            'address' => $this->nullable($row['address'] ?? null),
            'opening_balance' => (float) ($row['opening_balance'] ?: 0),
            'notes' => $this->nullable($row['notes'] ?? null),
            'is_active' => $this->booleanValue($row['is_active'] ?? 'oui', $row['_line'] ?? 0, 'actif'),
        ];

        $paymentTermId = $this->resolvePaymentTermId($companyId, $row['payment_term'] ?? null, $row['_line'] ?? 0);
        if ($paymentTermId !== null) {
            $attributes['payment_term_id'] = $paymentTermId;
        }

        $priceListId = $this->resolvePriceListId($companyId, $row['price_list'] ?? null, $row['_line'] ?? 0);
        if ($priceListId !== null) {
            $attributes['price_list_id'] = $priceListId;
        }

        $partner = null;

        if ($code) {
            $partner = Partner::query()->where('company_id', $companyId)->where('code', $code)->first();
        }

        if (! $partner && $email) {
            $partner = Partner::query()->where('company_id', $companyId)->where('email', $email);
            $partner = $scope($partner)->first();
        }

        if (! $partner) {
            $partner = Partner::query()->where('company_id', $companyId)->where('name', trim((string) $row['name']));
            $partner = $scope($partner)->first();
        }

        if ($partner) {
            $partner->update(array_merge($attributes, [
                'code' => $code ?: $partner->code,
            ]));
        } else {
            Partner::query()->create(array_merge($attributes, [
                'code' => $code ?: $this->generatePartnerCode($companyId, $type === 'supplier' ? 'F' : 'C'),
            ]));
        }
    }

    private function resolvePaymentTermId(int $companyId, mixed $value, int $line): ?int
    {
        $name = $this->nullable($value);

        if (! $name) {
            return null;
        }

        $paymentTerm = PaymentTerm::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($name): void {
                $query->where('name', $name)->orWhere('code', $name);
            })
            ->first();

        if (! $paymentTerm) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$line.': condition de paiement introuvable pour "'.$name.'".',
            ]);
        }

        return (int) $paymentTerm->id;
    }

    private function resolvePriceListId(int $companyId, mixed $value, int $line): ?int
    {
        $name = $this->nullable($value);

        if (! $name) {
            return null;
        }

        $priceList = PriceList::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($name): void {
                $query->where('name', $name)->orWhere('code', $name);
            })
            ->first();

        if (! $priceList) {
            throw ValidationException::withMessages([
                'import' => 'Ligne '.$line.': liste de prix introuvable pour "'.$name.'".',
            ]);
        }

        return (int) $priceList->id;
    }

    private function booleanValue(mixed $value, int $line, string $label): bool
    {
        $normalized = $this->nullable($value);

        if ($normalized === null) {
            return true;
        }

        $normalized = Str::of($normalized)->ascii()->lower()->trim()->value();

        if (in_array($normalized, ['1', 'oui', 'yes', 'true', 'vrai', 'actif', 'active'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'non', 'no', 'false', 'faux', 'inactif', 'inactive'], true)) {
            return false;
        }

        throw ValidationException::withMessages([
            'import' => 'Ligne '.$line.': valeur invalide pour '.$label.'. Utilise oui/non.',
        ]);
    }

    private function parseTabular(UploadedFile $file, array $expectedHeaders, array $alternativeHeaders = [], bool $allowExtraColumns = false): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->parseXlsx($file, $expectedHeaders, $alternativeHeaders, $allowExtraColumns);
        }

        return $this->parseCsv($file, $expectedHeaders, $alternativeHeaders, $allowExtraColumns);
    }

    private function parseCsv(UploadedFile $file, array $expectedHeaders, array $alternativeHeaders = [], bool $allowExtraColumns = false): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw ValidationException::withMessages([
                'file' => 'Impossible de lire le fichier importe.',
            ]);
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine ?: '');

        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);

        $acceptedHeaders = array_merge([$expectedHeaders], $alternativeHeaders);
        if (! $this->headersAccepted($headers, $acceptedHeaders, $allowExtraColumns)) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'Le fichier ne correspond pas au modele attendu. Colonnes attendues : '.implode(', ', $expectedHeaders).'.',
            ]);
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if ($data === [null] || collect($data)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $data = array_pad($data, count($headers), null);
            $row = array_combine($headers, array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data));
            $row['_line'] = $line;
            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier importe est vide.',
            ]);
        }

        return $rows;
    }

    private function parseXlsx(UploadedFile $file, array $expectedHeaders, array $alternativeHeaders = [], bool $allowExtraColumns = false): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'file' => 'Le serveur ne peut pas lire les fichiers Excel XLSX pour le moment.',
            ]);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'file' => 'Impossible de lire le fichier Excel importe.',
            ]);
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (! $sheetXml) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier Excel ne contient pas de feuille lisible.',
            ]);
        }

        $sharedStrings = $this->xlsxSharedStrings($sharedStringsXml ?: '');
        $sheet = simplexml_load_string($sheetXml);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $sheet->xpath('//x:sheetData/x:row') ?: [];
        $rawRows = [];

        foreach ($rowNodes as $rowNode) {
            $rowIndex = (int) $rowNode['r'];
            $values = [];

            foreach ($rowNode->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->xlsxColumnIndex($reference);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$columnIndex] = trim($value);
            }

            if ($values !== []) {
                ksort($values);
                $rawRows[$rowIndex] = $values;
            }
        }

        if ($rawRows === []) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier Excel importe est vide.',
            ]);
        }

        $firstRow = array_shift($rawRows);
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_values($firstRow));
        $acceptedHeaders = array_merge([$expectedHeaders], $alternativeHeaders);

        if (! $this->headersAccepted($headers, $acceptedHeaders, $allowExtraColumns)) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier ne correspond pas au modele attendu. Colonnes attendues : '.implode(', ', $expectedHeaders).'.',
            ]);
        }

        $rows = [];
        foreach ($rawRows as $line => $values) {
            $data = [];
            for ($index = 0; $index < count($headers); $index++) {
                $data[] = $values[$index] ?? null;
            }

            if (collect($data)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $row = array_combine($headers, array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data));
            $row['_line'] = $line;
            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier Excel importe est vide.',
            ]);
        }

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::of($header)
            ->ascii()
            ->lower()
            ->replace([' ', '-', '/', '.'], '_')
            ->replace('__', '_')
            ->trim()
            ->value();

        return $this->headerAliases()[$normalized] ?? $normalized;
    }

    private function headersAccepted(array $headers, array $acceptedHeaders, bool $allowExtraColumns = false): bool
    {
        foreach ($acceptedHeaders as $expected) {
            if ($headers === $expected) {
                return true;
            }

            if ($allowExtraColumns && empty(array_diff($expected, $headers))) {
                return true;
            }
        }

        return false;
    }

    private function partnerImportHeaders(): array
    {
        return [
            'code',
            'name',
            'phone',
            'email',
            'city',
            'nif',
            'address',
            'opening_balance',
            'notes',
        ];
    }

    private function headerAliases(): array
    {
        return [
            'nom' => 'name',
            'raison_sociale' => 'name',
            'client' => 'name',
            'fournisseur' => 'name',
            'telephone' => 'phone',
            'tel' => 'phone',
            'mobile' => 'phone',
            'whatsapp' => 'phone',
            'ville' => 'city',
            'commune' => 'city',
            'adresse' => 'address',
            'solde_initial' => 'opening_balance',
            'solde_ouverture' => 'opening_balance',
            'balance_initiale' => 'opening_balance',
            'condition_paiement' => 'payment_term',
            'conditions_paiement' => 'payment_term',
            'delai_paiement' => 'payment_term',
            'payment_terms' => 'payment_term',
            'payment_term_name' => 'payment_term',
            'liste_prix' => 'price_list',
            'liste_de_prix' => 'price_list',
            'tarif' => 'price_list',
            'price_list_name' => 'price_list',
            'actif' => 'is_active',
            'active' => 'is_active',
            'statut' => 'is_active',
            'remarque' => 'notes',
            'commentaire' => 'notes',
        ];
    }

    private function existingProductId(int $companyId, ?string $sku, ?string $barcode, ?string $name): ?int
    {
        if ($sku) {
            $id = Product::query()->where('company_id', $companyId)->where('sku', $sku)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($barcode) {
            $id = Product::query()->where('company_id', $companyId)->where('barcode', $barcode)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($name) {
            $id = Product::query()->where('company_id', $companyId)->where('name', trim($name))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private function xlsxSheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->xlsxColumnName($columnIndex + 1).$rowNumber;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function xlsxSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $shared->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return collect($shared->xpath('//x:si') ?: [])
            ->map(fn ($node) => (string) collect($node->xpath('.//x:t') ?: [])->map(fn ($text) => (string) $text)->implode(''))
            ->values()
            ->all();
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function generatePartnerCode(int $companyId, string $prefix): string
    {
        $documentType = match (strtoupper($prefix)) {
            'C' => 'partner_customer_code',
            'F' => 'partner_supplier_code',
            default => 'partner_generic_code',
        };

        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, $documentType);
    }

    private function generateSku(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'product_sku');
    }

    private function defaultBarcodeFromSku(int $companyId, ?string $sku, ?int $ignoreId = null): ?string
    {
        $barcode = trim((string) $sku);

        if ($barcode === '') {
            return null;
        }

        $exists = Product::query()
            ->where('company_id', $companyId)
            ->where('barcode', $barcode)
            ->when($ignoreId, fn ($query, int $productId) => $query->whereKeyNot($productId))
            ->exists();

        return $exists ? null : $barcode;
    }
}
