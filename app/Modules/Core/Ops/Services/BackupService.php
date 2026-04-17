<?php

namespace App\Modules\Core\Ops\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class BackupService
{
    public function create(?int $keep = null): array
    {
        $keep = max($keep ?? (int) config('ops.backup_retention', 7), 1);
        $basePath = $this->basePath();

        File::ensureDirectoryExists($basePath);

        $stamp = now()->format('Ymd_His');
        $backupPath = $basePath.DIRECTORY_SEPARATOR.$stamp;
        $databasePath = $backupPath.DIRECTORY_SEPARATOR.'database';
        $assetsPath = $backupPath.DIRECTORY_SEPARATOR.'assets';

        File::ensureDirectoryExists($databasePath);
        File::ensureDirectoryExists($assetsPath);

        $tables = Schema::getTableListing();
        $tableExports = [];
        $totalRows = 0;

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()
                ->map(fn (object $row): array => $this->normalizeRow((array) $row))
                ->all();

            $relativePath = 'database/'.$table.'.json';
            File::put(
                $backupPath.DIRECTORY_SEPARATOR.$relativePath,
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $rowCount = count($rows);
            $totalRows += $rowCount;

            $tableExports[] = [
                'table' => $table,
                'rows' => $rowCount,
                'path' => $relativePath,
            ];
        }

        $assetExports = [];

        foreach ($this->assetSources() as $asset) {
            $source = $asset['source'];

            if (! File::isDirectory($source)) {
                continue;
            }

            $fileCount = $this->countFiles($source);

            if ($fileCount === 0) {
                continue;
            }

            $relativePath = 'assets/'.$asset['key'];
            File::copyDirectory($source, $backupPath.DIRECTORY_SEPARATOR.$relativePath);

            $assetExports[] = [
                'label' => $asset['label'],
                'files' => $fileCount,
                'path' => $relativePath,
            ];
        }

        $manifest = [
            'created_at' => now()->toDateTimeString(),
            'database_connection' => config('database.default'),
            'tables' => $tableExports,
            'tables_count' => count($tableExports),
            'total_rows' => $totalRows,
            'assets' => $assetExports,
            'assets_count' => count($assetExports),
            'retention_keep' => $keep,
        ];

        File::put(
            $backupPath.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $manifest['directory'] = $backupPath;
        $manifest['manifest_path'] = $backupPath.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest['pruned_count'] = $this->prune($keep);

        File::put(
            $backupPath.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode(collect($manifest)->except(['directory', 'manifest_path'])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $manifest;
    }

    public function latest(): ?array
    {
        return $this->manifests()->first();
    }

    public function verify(?string $path = null): array
    {
        $manifest = $path ? $this->manifestFromPath($path) : $this->latest();

        if (! $manifest) {
            return [
                'status' => 'warning',
                'message' => 'Aucune sauvegarde exploitable n a ete trouvee.',
                'errors' => [],
                'warnings' => ['Aucune sauvegarde disponible'],
                'tables_expected' => 0,
                'tables_checked' => 0,
                'verified_rows' => 0,
                'assets_expected' => 0,
                'assets_checked' => 0,
                'asset_files_verified' => 0,
                'created_at' => null,
                'directory' => null,
                'manifest_path' => null,
            ];
        }

        $errors = [];
        $warnings = [];
        $tablesChecked = 0;
        $verifiedRows = 0;
        $assetsChecked = 0;
        $assetFilesVerified = 0;
        $tableEntries = collect($manifest['tables'] ?? []);
        $assetEntries = collect($manifest['assets'] ?? []);

        foreach ($tableEntries as $table) {
            $tablePath = $manifest['directory'].DIRECTORY_SEPARATOR.($table['path'] ?? '');

            if (! File::exists($tablePath)) {
                $errors[] = 'Fichier table manquant : '.($table['path'] ?? 'inconnu');

                continue;
            }

            try {
                $decoded = json_decode(File::get($tablePath), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $errors[] = 'JSON table invalide : '.($table['table'] ?? basename($tablePath)).' ('.$exception->getMessage().')';

                continue;
            }

            if (! is_array($decoded)) {
                $errors[] = 'Contenu table invalide : '.($table['table'] ?? basename($tablePath));

                continue;
            }

            $rowCount = count($decoded);
            $expectedRows = (int) ($table['rows'] ?? 0);

            if ($rowCount !== $expectedRows) {
                $errors[] = 'Volume incoherent pour '.($table['table'] ?? basename($tablePath)).' : '.$rowCount.' ligne(s) lue(s) pour '.$expectedRows.' attendue(s).';
            }

            $tablesChecked++;
            $verifiedRows += $rowCount;
        }

        foreach ($assetEntries as $asset) {
            $assetPath = $manifest['directory'].DIRECTORY_SEPARATOR.($asset['path'] ?? '');

            if (! File::isDirectory($assetPath)) {
                $errors[] = 'Dossier asset manquant : '.($asset['path'] ?? 'inconnu');

                continue;
            }

            $files = $this->countFiles($assetPath);
            $expectedFiles = (int) ($asset['files'] ?? 0);

            if ($files !== $expectedFiles) {
                $errors[] = 'Volume asset incoherent pour '.($asset['label'] ?? basename($assetPath)).' : '.$files.' fichier(s) lu(s) pour '.$expectedFiles.' attendu(s).';
            }

            $assetsChecked++;
            $assetFilesVerified += $files;
        }

        if ($tableEntries->isEmpty()) {
            $warnings[] = 'La sauvegarde ne contient aucune table exportee.';
        }

        $status = ! empty($errors) ? 'fail' : (! empty($warnings) ? 'warning' : 'ok');
        $message = match ($status) {
            'fail' => 'La sauvegarde existe mais son integrite est invalide.',
            'warning' => 'La sauvegarde est lisible mais demande une verification complementaire.',
            default => 'La sauvegarde est lisible et coherente.',
        };

        return [
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'warnings' => $warnings,
            'tables_expected' => $tableEntries->count(),
            'tables_checked' => $tablesChecked,
            'verified_rows' => $verifiedRows,
            'assets_expected' => $assetEntries->count(),
            'assets_checked' => $assetsChecked,
            'asset_files_verified' => $assetFilesVerified,
            'created_at' => $manifest['created_at'] ?? null,
            'directory' => $manifest['directory'] ?? null,
            'manifest_path' => $manifest['manifest_path'] ?? null,
        ];
    }

    public function restorePreview(?string $path = null): array
    {
        $manifest = $path ? $this->manifestFromPath($path) : $this->latest();
        $verification = $this->verify($path);

        if (! $manifest) {
            return [
                'available' => false,
                'title' => 'Aucun plan de restauration disponible',
                'summary' => 'Genere d abord une sauvegarde locale exploitable avant de documenter la reprise.',
                'verification_status' => $verification['status'],
                'steps' => [
                    'Lance php artisan nema:ops:backup-run --keep=7 pour generer un premier point de reprise.',
                    'Controle ensuite la sauvegarde avec php artisan nema:ops:backup-verify.',
                    'Reviens enfin sur l ecran Ops pour consulter le plan de reprise.',
                ],
            ];
        }

        return [
            'available' => true,
            'title' => 'Procedure de reprise locale',
            'summary' => 'Le dernier point de reprise date du '.($manifest['created_at'] ?? 'N/A').' et contient '.((int) ($manifest['tables_count'] ?? 0)).' table(s) exportee(s).',
            'verification_status' => $verification['status'],
            'directory' => $manifest['directory'] ?? null,
            'created_at' => $manifest['created_at'] ?? null,
            'steps' => [
                'Gele les ecritures et conserve une copie de l instance en panne avant toute manipulation.',
                'Verifie le backup choisi avec php artisan nema:ops:backup-verify pour confirmer son integrite.',
                'Prepare une instance saine, renseigne le .env cible puis lance php artisan migrate --force.',
                'Recharge les exports JSON du dossier database/ en suivant l ordre metier critique: referentiels, stock, ventes, achats, tresorerie.',
                'Recopie ensuite les assets du dossier assets/ vers storage/app/public ou public/media selon leur emplacement.',
                'Termine par php artisan nema:ops:health-check --store puis teste POS, ventes, achats et rapports avant reouverture.',
            ],
        ];
    }

    public function prune(?int $keep = null): int
    {
        $keep = max($keep ?? (int) config('ops.backup_retention', 7), 1);
        $manifests = $this->manifests();

        if ($manifests->count() <= $keep) {
            return 0;
        }

        $deleted = 0;

        foreach ($manifests->slice($keep) as $manifest) {
            if (File::deleteDirectory($manifest['directory'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function syncLatestToOffsite(?string $disk = null, ?string $prefix = null): array
    {
        $manifest = $this->latest();

        if (! $manifest) {
            return [
                'status' => 'warning',
                'message' => 'Aucune sauvegarde locale disponible pour synchronisation hors machine.',
                'uploaded_files' => 0,
                'uploaded_bytes' => 0,
            ];
        }

        $disk = $disk ?: (string) config('ops.backup_offsite_disk', '');
        $prefix = trim($prefix ?: (string) config('ops.backup_offsite_prefix', 'nema-erp/backups'), '/');

        if (! $this->offsiteDiskAvailable($disk)) {
            return [
                'status' => 'fail',
                'message' => 'Disque hors machine invalide ou non configure.',
                'uploaded_files' => 0,
                'uploaded_bytes' => 0,
                'disk' => $disk,
                'prefix' => $prefix,
            ];
        }

        $sourceDirectory = (string) ($manifest['directory'] ?? '');
        $backupName = basename($sourceDirectory);
        $remoteRoot = ltrim($prefix.'/'.$backupName, '/');
        $remoteManifestPath = $remoteRoot.'/sync-manifest.json';
        $existingManifest = Storage::disk($disk)->exists($remoteManifestPath)
            ? json_decode((string) Storage::disk($disk)->get($remoteManifestPath), true)
            : [];
        $existingFiles = collect($existingManifest['files'] ?? [])->keyBy('path');
        $uploadedFiles = 0;
        $uploadedBytes = 0;
        $skippedFiles = 0;
        $filesManifest = [];

        foreach (File::allFiles($sourceDirectory) as $file) {
            $relativePath = str_replace($sourceDirectory.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $remotePath = str_replace(DIRECTORY_SEPARATOR, '/', $remoteRoot.'/'.$relativePath);
            $checksum = sha1_file($file->getPathname()) ?: '';
            $size = (int) $file->getSize();

            $filesManifest[] = [
                'path' => $relativePath,
                'checksum' => $checksum,
                'size' => $size,
            ];

            $existing = $existingFiles->get($relativePath);

            if ($existing && ($existing['checksum'] ?? '') === $checksum) {
                $skippedFiles++;

                continue;
            }

            $content = File::get($file->getPathname());

            Storage::disk($disk)->put($remotePath, $content);

            $uploadedFiles++;
            $uploadedBytes += strlen($content);
        }

        Storage::disk($disk)->put($remoteManifestPath, json_encode([
            'synced_at' => now()->toDateTimeString(),
            'created_at' => $manifest['created_at'] ?? null,
            'source_directory' => $sourceDirectory,
            'uploaded_files' => $uploadedFiles,
            'uploaded_bytes' => $uploadedBytes,
            'skipped_files' => $skippedFiles,
            'files' => $filesManifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Storage::disk($disk)->put($remoteRoot.'/sync-meta.json', json_encode([
            'synced_at' => now()->toDateTimeString(),
            'created_at' => $manifest['created_at'] ?? null,
            'source_directory' => $sourceDirectory,
            'uploaded_files' => $uploadedFiles,
            'uploaded_bytes' => $uploadedBytes,
            'skipped_files' => $skippedFiles,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pruned = $this->pruneOffsite($disk, $prefix, max((int) config('ops.backup_offsite_keep', 14), 1));

        return [
            'status' => 'ok',
            'message' => 'Sauvegarde synchronisee hors machine avec succes.',
            'uploaded_files' => $uploadedFiles,
            'uploaded_bytes' => $uploadedBytes,
            'skipped_files' => $skippedFiles,
            'pruned_backups' => $pruned,
            'disk' => $disk,
            'prefix' => $remoteRoot,
            'created_at' => $manifest['created_at'] ?? null,
        ];
    }

    public function verifyLatestOffsite(?string $disk = null, ?string $prefix = null): array
    {
        $disk = $disk ?: (string) config('ops.backup_offsite_disk', '');
        $prefix = trim($prefix ?: (string) config('ops.backup_offsite_prefix', 'nema-erp/backups'), '/');

        if (! $this->offsiteDiskAvailable($disk)) {
            return [
                'status' => 'fail',
                'message' => 'Disque hors machine invalide ou non configure.',
            ];
        }

        $backups = $this->offsiteBackupNames($disk, $prefix);
        $latest = $backups[0] ?? null;

        if (! $latest) {
            return [
                'status' => 'warning',
                'message' => 'Aucune sauvegarde hors machine disponible.',
                'checked_files' => 0,
            ];
        }

        $manifestPath = $prefix.'/'.$latest.'/sync-manifest.json';

        if (! Storage::disk($disk)->exists($manifestPath)) {
            return [
                'status' => 'fail',
                'message' => 'Manifest distant manquant pour le dernier backup.',
                'checked_files' => 0,
            ];
        }

        $manifest = json_decode((string) Storage::disk($disk)->get($manifestPath), true);
        $files = collect($manifest['files'] ?? []);
        $missing = $files->filter(fn (array $file): bool => ! Storage::disk($disk)->exists($prefix.'/'.$latest.'/'.($file['path'] ?? '')))->count();

        return [
            'status' => $missing > 0 ? 'fail' : 'ok',
            'message' => $missing > 0
                ? 'Des fichiers sont manquants sur le backup hors machine.'
                : 'Le dernier backup hors machine est lisible et complet.',
            'checked_files' => $files->count(),
            'missing_files' => $missing,
            'disk' => $disk,
            'prefix' => $prefix.'/'.$latest,
        ];
    }

    private function pruneOffsite(string $disk, string $prefix, int $keep): int
    {
        $backups = $this->offsiteBackupNames($disk, $prefix);

        if (count($backups) <= $keep) {
            return 0;
        }

        $deleted = 0;

        foreach (array_slice($backups, $keep) as $backupName) {
            Storage::disk($disk)->deleteDirectory($prefix.'/'.$backupName);
            $deleted++;
        }

        return $deleted;
    }

    private function offsiteBackupNames(string $disk, string $prefix): array
    {
        $files = Storage::disk($disk)->allFiles($prefix);
        $prefixWithSlash = rtrim($prefix, '/').'/';
        $names = collect($files)
            ->map(function (string $path) use ($prefixWithSlash): ?string {
                if (! str_starts_with($path, $prefixWithSlash)) {
                    return null;
                }

                $relative = substr($path, strlen($prefixWithSlash));

                return explode('/', $relative)[0] ?? null;
            })
            ->filter(fn (?string $name): bool => filled($name))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $names;
    }

    private function offsiteDiskAvailable(?string $disk): bool
    {
        if (! filled($disk)) {
            return false;
        }

        try {
            Storage::disk((string) $disk);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function manifests(): Collection
    {
        $basePath = $this->basePath();

        if (! File::isDirectory($basePath)) {
            return collect();
        }

        return collect(File::directories($basePath))
            ->map(fn (string $directory): ?array => $this->manifestFromPath($directory))
            ->filter()
            ->sortByDesc(fn (array $manifest): string => (string) ($manifest['created_at'] ?? basename($manifest['directory'])))
            ->values();
    }

    private function manifestFromPath(string $path): ?array
    {
        $manifestPath = str_ends_with($path, 'manifest.json') ? $path : rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest.json';
        $directory = dirname($manifestPath);

        if (! File::exists($manifestPath)) {
            return null;
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return array_merge($manifest, [
            'directory' => $directory,
            'manifest_path' => $manifestPath,
        ]);
    }

    private function normalizeRow(array $row): array
    {
        return collect($row)->map(fn (mixed $value): mixed => $this->normalizeValue($value))->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_resource($value)) {
            return null;
        }

        return $value;
    }

    private function assetSources(): array
    {
        return [
            [
                'key' => 'storage-public',
                'label' => 'Storage public',
                'source' => storage_path('app/public'),
            ],
            [
                'key' => 'public-media',
                'label' => 'Public media',
                'source' => public_path('media'),
            ],
        ];
    }

    private function countFiles(string $directory): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $count = 0;

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function basePath(): string
    {
        return (string) config('ops.backups_path', storage_path('app/backups'));
    }
}
