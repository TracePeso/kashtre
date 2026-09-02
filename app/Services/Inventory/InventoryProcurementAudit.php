<?php

namespace App\Services\Inventory;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Explicit who / when / why audit entries for procurement workflow actions.
 */
class InventoryProcurementAudit
{
    public static function log(
        string $action,
        Model $model,
        string $description,
        ?string $why = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $user = Auth::user();

        $payload = array_filter([
            'why' => $why,
            'details' => $newValues,
        ], fn ($v) => $v !== null && $v !== []);

        ActivityLog::create([
            'user_id' => $user?->id,
            'business_id' => $user?->business_id ?? ($model->business_id ?? null),
            'branch_id' => $user?->branch_id,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'action' => $action,
            'action_type' => 'procurement',
            'old_values' => $oldValues,
            'new_values' => $payload !== [] ? $payload : $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'description' => $description.($why ? ' — Why: '.$why : ''),
            'date' => now()->toDateString(),
        ]);
    }
}
