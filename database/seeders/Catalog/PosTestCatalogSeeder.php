<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;

class PosTestCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $category = ProductCategory::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Tests POS'],
            [
                'description' => 'Articles dedies aux tests de caisse et de ticket.',
                'is_active' => true,
            ]
        );

        $products = [
            [
                'sku' => 'POS-TEST-001',
                'barcode' => '223900000001',
                'name' => 'POS Test Eau 50cl',
                'unit' => 'bouteille',
                'type' => 'stockable',
                'purchase_price' => 120,
                'sale_price' => 250,
                'min_stock' => 5,
                'description' => 'Article simple pour tester les ventes rapides en caisse.',
            ],
            [
                'sku' => 'POS-TEST-002',
                'barcode' => '223900000002',
                'name' => 'POS Test Biscuit 100g',
                'unit' => 'paquet',
                'type' => 'stockable',
                'purchase_price' => 180,
                'sale_price' => 350,
                'min_stock' => 5,
                'description' => 'Article de demonstration pour test panier multi-lignes.',
            ],
            [
                'sku' => 'POS-TEST-003',
                'barcode' => '223900000003',
                'name' => 'POS Test Jus Orange 1L',
                'unit' => 'brique',
                'type' => 'stockable',
                'purchase_price' => 475,
                'sale_price' => 700,
                'min_stock' => 5,
                'description' => 'Article test pour quantites et remises POS.',
            ],
            [
                'sku' => 'POS-TEST-004',
                'barcode' => '223900000004',
                'name' => 'POS Test Kit Famille',
                'unit' => 'kit',
                'type' => 'stockable',
                'purchase_price' => 1500,
                'sale_price' => 2250,
                'min_stock' => 3,
                'description' => 'Article a prix plus eleve pour tester les paiements et la monnaie.',
            ],
            [
                'sku' => 'POS-TEST-005',
                'barcode' => '223900000005',
                'name' => 'POS Test Service Livraison',
                'unit' => 'service',
                'type' => 'service',
                'purchase_price' => 0,
                'sale_price' => 3000,
                'min_stock' => 0,
                'description' => 'Service de demonstration pour tester un ticket sans stock.',
            ],
        ];

        foreach ($products as $data) {
            Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $data['sku']],
                [
                    'category_id' => $category->id,
                    'barcode' => $data['barcode'],
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'type' => $data['type'],
                    'sale_ok' => true,
                    'purchase_ok' => $data['type'] === 'stockable',
                    'sale_blocked' => false,
                    'purchase_blocked' => false,
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'min_stock' => $data['min_stock'],
                    'description' => $data['description'],
                    'sales_description' => $data['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
