<?php

namespace App\Jobs;

use App\Models\ProvisioningLog;
use App\Services\ProvisioningOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RetryProvisioningJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $provisioningLogId) {}

    public function handle(ProvisioningOrchestrator $orchestrator): void
    {
        $log = ProvisioningLog::query()->find($this->provisioningLogId);

        if ($log === null || $log->status === 'success') {
            return;
        }

        try {
            $orchestrator->retry($log);
        } catch (\Throwable $exception) {
            Log::error('Provisioning retry failed', [
                'log_id' => $log->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
