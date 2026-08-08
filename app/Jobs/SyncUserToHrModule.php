<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\HrModuleSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncUserToHrModule implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
    ) {
    }

    public function handle(HrModuleSyncService $hrSync): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $hrSync->pushUsers($user);
    }
}
