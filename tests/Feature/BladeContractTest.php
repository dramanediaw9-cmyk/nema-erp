<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BladeContractTest extends TestCase
{
    public function test_literal_route_references_in_blade_templates_exist(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/route\(\s*[\'\"]([^\'\"]+)[\'\"]/', (string) file_get_contents($file), $matches);
            foreach (array_unique($matches[1] ?? []) as $routeName) {
                $contents = (string) file_get_contents($file);
                $isGuarded = str_contains($contents, "Route::has('{$routeName}')")
                    || str_contains($contents, 'Route::has("'.$routeName.'")');

                if (! Route::has($routeName) && ! $isGuarded) {
                    $missing[] = $this->relativePath($file).': '.$routeName;
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }

    public function test_literal_public_assets_in_blade_templates_exist(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/asset\(\s*[\'\"]([^\'\"]+)[\'\"]/', (string) file_get_contents($file), $matches);
            foreach (array_unique($matches[1] ?? []) as $asset) {
                if (str_ends_with($asset, '/')) {
                    continue;
                }

                $path = public_path(parse_url($asset, PHP_URL_PATH) ?: $asset);
                if (! is_file($path)) {
                    $missing[] = $this->relativePath($file).': '.$asset;
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
