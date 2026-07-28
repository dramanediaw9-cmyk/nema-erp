<?php

namespace App\Modules\Core\Imports\Odoo\Jobs;

use App\Modules\Core\Imports\Odoo\Models\OdooProductImportError;
use App\Modules\Core\Imports\Odoo\Models\OdooProductImportRun;
use App\Modules\Core\Imports\Odoo\Services\OdooProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOdooProductImportBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $runId,
        public readonly bool $dispatchNext = true,
    ) {
        $this->onQueue((string) config('odoo.queue', 'imports'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('odoo-product-run-'.$this->runId))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 30)
                ->shared(),
        ];
    }

    public function handle(OdooProductImportService $service): void
    {
        $run = OdooProductImportRun::query()->find($this->runId);
        if (! $run || in_array($run->status, ['completed', 'cancelled'], true)) {
            return;
        }

        try {
            if ($service->processNextBatch($run) && $this->dispatchNext) {
                self::dispatch($run->id);
            }
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
                'heartbeat_at' => now(),
            ])->save();

            OdooProductImportError::query()->create([
                'odoo_product_import_run_id' => $run->id,
                'odoo_model' => $run->phase === 'templates' ? 'product.template' : 'product.product',
                'phase' => $run->phase,
                'message' => $exception->getMessage(),
                'context' => ['attempt' => $this->attempts()],
                'retryable' => true,
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        OdooProductImportRun::query()
            ->whereKey($this->runId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update([
                'status' => 'failed',
                'last_error' => $exception?->getMessage() ?: 'Le traitement en file a echoue.',
                'heartbeat_at' => now(),
            ]);
    }
}
