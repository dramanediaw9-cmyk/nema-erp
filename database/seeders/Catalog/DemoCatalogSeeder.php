<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $categories = collect([
            ['name' => 'Boissons', 'description' => 'Produits de boisson et rafraichissement'],
            ['name' => 'Epicerie', 'description' => 'Produits de consommation courante'],
            ['name' => 'Services', 'description' => 'Prestations non stockees'],
        ])->mapWithKeys(function (array $data) use ($company) {
            $category = ProductCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                [
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );

            return [$data['name'] => $category];
        });

        $products = [
            [
                'sku' => 'PRD-0001',
                'barcode' => '223000000001',
                'name' => 'Eau minerale 1.5L',
                'category' => 'Boissons',
                'unit' => 'bouteille',
                'type' => 'stockable',
                'purchase_price' => 250,
                'sale_price' => 400,
                'min_stock' => 30,
                'accent' => '#2563eb',
            ],
            [
                'sku' => 'PRD-0002',
                'barcode' => '223000000002',
                'name' => 'Sucre 1kg',
                'category' => 'Epicerie',
                'unit' => 'sachet',
                'type' => 'stockable',
                'purchase_price' => 500,
                'sale_price' => 700,
                'min_stock' => 20,
                'accent' => '#ca8a04',
            ],
            [
                'sku' => 'PRD-0003',
                'barcode' => '223000000003',
                'name' => 'Livraison intra-Bamako',
                'category' => 'Services',
                'unit' => 'service',
                'type' => 'service',
                'purchase_price' => 0,
                'sale_price' => 5000,
                'min_stock' => 0,
                'accent' => '#059669',
            ],
            [
                'sku' => 'PRD-0005',
                'barcode' => '223000000005',
                'name' => 'Jus mangue 1L',
                'category' => 'Boissons',
                'unit' => 'brique',
                'type' => 'stockable',
                'purchase_price' => 650,
                'sale_price' => 900,
                'min_stock' => 18,
                'accent' => '#f59e0b',
            ],
            [
                'sku' => 'PRD-0006',
                'barcode' => '223000000006',
                'name' => 'Boisson gazeuse cola 33cl',
                'category' => 'Boissons',
                'unit' => 'canette',
                'type' => 'stockable',
                'purchase_price' => 300,
                'sale_price' => 500,
                'min_stock' => 24,
                'accent' => '#dc2626',
            ],
            [
                'sku' => 'PRD-0007',
                'barcode' => '223000000007',
                'name' => 'Riz Gambiaka 5kg',
                'category' => 'Epicerie',
                'unit' => 'sac',
                'type' => 'stockable',
                'purchase_price' => 3800,
                'sale_price' => 4500,
                'min_stock' => 12,
                'accent' => '#0f766e',
            ],
            [
                'sku' => 'PRD-0008',
                'barcode' => '223000000008',
                'name' => 'Huile vegetale 1L',
                'category' => 'Epicerie',
                'unit' => 'bouteille',
                'type' => 'stockable',
                'purchase_price' => 1150,
                'sale_price' => 1450,
                'min_stock' => 20,
                'accent' => '#d97706',
            ],
            [
                'sku' => 'PRD-0009',
                'barcode' => '223000000009',
                'name' => 'Spaghetti 500g',
                'category' => 'Epicerie',
                'unit' => 'paquet',
                'type' => 'stockable',
                'purchase_price' => 325,
                'sale_price' => 500,
                'min_stock' => 24,
                'accent' => '#7c3aed',
            ],
            [
                'sku' => 'PRD-0010',
                'barcode' => '223000000010',
                'name' => 'Sardines tomate 125g',
                'category' => 'Epicerie',
                'unit' => 'boite',
                'type' => 'stockable',
                'purchase_price' => 425,
                'sale_price' => 650,
                'min_stock' => 24,
                'accent' => '#ef4444',
            ],
            [
                'sku' => 'PRD-0011',
                'barcode' => '223000000011',
                'name' => 'Biscuits beurre 150g',
                'category' => 'Epicerie',
                'unit' => 'paquet',
                'type' => 'stockable',
                'purchase_price' => 220,
                'sale_price' => 350,
                'min_stock' => 20,
                'accent' => '#f97316',
            ],
            [
                'sku' => 'PRD-0012',
                'barcode' => '223000000012',
                'name' => 'The vert Attaya 125g',
                'category' => 'Epicerie',
                'unit' => 'paquet',
                'type' => 'stockable',
                'purchase_price' => 700,
                'sale_price' => 950,
                'min_stock' => 16,
                'accent' => '#16a34a',
            ],
            [
                'sku' => 'PRD-0013',
                'barcode' => '223000000013',
                'name' => 'Farine de ble 1kg',
                'category' => 'Epicerie',
                'unit' => 'sachet',
                'type' => 'stockable',
                'purchase_price' => 450,
                'sale_price' => 650,
                'min_stock' => 18,
                'accent' => '#a16207',
            ],
            [
                'sku' => 'PRD-0014',
                'barcode' => '223000000014',
                'name' => 'Tomate concentree 400g',
                'category' => 'Epicerie',
                'unit' => 'boite',
                'type' => 'stockable',
                'purchase_price' => 375,
                'sale_price' => 550,
                'min_stock' => 20,
                'accent' => '#b91c1c',
            ],
        ];

        foreach ($products as $data) {
            $product = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $data['sku']],
                [
                    'category_id' => $categories[$data['category']]->id,
                    'barcode' => $data['barcode'],
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'type' => $data['type'],
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'min_stock' => $data['min_stock'],
                    'is_active' => true,
                ]
            );

            if (! $product->image_path) {
                $product->update([
                    'image_path' => $this->storeDemoVisual($product->sku, $data['name'], $data['category'], $data['accent']),
                ]);
            }
        }
    }

    private function storeDemoVisual(string $sku, string $name, string $category, string $accent): string
    {
        $path = 'products/demo-'.Str::slug($sku).'.svg';
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $this->demoSvg($name, $category, $accent));
        }

        return $path;
    }

    private function demoSvg(string $name, string $category, string $accent): string
    {
        $title = e($name);
        $subtitle = e($category);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200" role="img" aria-label="{$title}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f8fbff"/>
      <stop offset="100%" stop-color="#e8f0ff"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="1200" rx="72" fill="url(#bg)"/>
  <circle cx="960" cy="240" r="180" fill="{$accent}" opacity="0.14"/>
  <circle cx="250" cy="980" r="210" fill="{$accent}" opacity="0.10"/>
  <rect x="116" y="120" width="968" height="960" rx="56" fill="#ffffff" stroke="#d6e3fb" stroke-width="8"/>
  <rect x="176" y="196" width="360" height="360" rx="48" fill="{$accent}" opacity="0.12"/>
  <rect x="206" y="226" width="300" height="300" rx="38" fill="#ffffff"/>
  <circle cx="356" cy="356" r="118" fill="{$accent}" opacity="0.12"/>
  <path d="M288 356c0-38 31-69 68-69h10c37 0 68 31 68 69v88c0 38-31 69-68 69h-10c-37 0-68-31-68-69z" fill="{$accent}" opacity="0.92"/>
  <rect x="608" y="246" width="416" height="56" rx="20" fill="#dbe7fb"/>
  <rect x="608" y="340" width="330" height="42" rx="18" fill="#e7eefb"/>
  <rect x="176" y="646" width="848" height="74" rx="24" fill="#f4f7fc"/>
  <rect x="176" y="754" width="662" height="40" rx="18" fill="#edf3fd"/>
  <rect x="176" y="836" width="504" height="40" rx="18" fill="#edf3fd"/>
  <text x="608" y="520" font-family="Segoe UI, Arial, sans-serif" font-size="76" font-weight="700" fill="#14324c">{$title}</text>
  <text x="608" y="594" font-family="Segoe UI, Arial, sans-serif" font-size="36" font-weight="600" fill="#4f6782">{$subtitle}</text>
  <text x="176" y="984" font-family="Segoe UI, Arial, sans-serif" font-size="30" font-weight="600" fill="#6b7e95">Visuel de demonstration ERP</text>
</svg>
SVG;
    }
}
