<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'products/'), 404);

        $product = Product::query()
            ->where('image_path', $path)
            ->latest('id')
            ->first();

        $disk = $product?->imageDisk() ?: config('nema.product_media_disk', 'public');

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
