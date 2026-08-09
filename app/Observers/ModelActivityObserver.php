<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ModelActivityObserver
{
    public function created(Model $model)
    {
        $this->log('created', $model);
    }

    public function updated(Model $model)
    {
        $this->log('updated', $model, $model->getOriginal());
    }

    public function deleted(Model $model)
    {
        $this->log('deleted', $model, $model->getOriginal());
    }

    protected function log(string $action, Model $model, $oldData = null)
    {
        // Skip logging if not authenticated (e.g., during seeding or console commands)
        if (!Auth::check()) {
            return;
        }

        // Generate meaningful description
        $modelName = class_basename($model);
        $description = $this->generateDescription($action, $model, $modelName);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'business_id' => optional(Auth::user())->business_id,
            'branch_id'   => optional(Auth::user())->branch_id,
            'model_type'  => get_class($model),
            'model_id'    => $model->getKey(),
            'action'      => $action,
            'old_values'  => $oldData ? json_encode($oldData) : null,
            'new_values'  => in_array($action, ['created', 'updated']) ? json_encode($model->getAttributes()) : null,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->header('User-Agent'),
            'description' => $description,
        ]);
    }

    /**
     * Generate a meaningful description for the activity log
     * Updated to provide better audit trail descriptions
     */
    protected function generateDescription(string $action, Model $model, string $modelName): string
    {
        $userName = Auth::user() ? Auth::user()->name : 'System';
        
        // Try to get a meaningful identifier for the model
        $identifier = $this->getModelIdentifier($model);
        
        switch ($action) {
            case 'created':
                return "{$userName} created a new {$modelName}" . ($identifier ? " ({$identifier})" : '');
            case 'updated':
                return "{$userName} updated {$modelName}" . ($identifier ? " ({$identifier})" : '');
            case 'deleted':
                return "{$userName} deleted {$modelName}" . ($identifier ? " ({$identifier})" : '');
            default:
                return "{$userName} performed {$action} on {$modelName}" . ($identifier ? " ({$identifier})" : '');
        }
    }

    /**
     * Get a meaningful identifier for the model (name, title, etc.)
     */
    protected function getModelIdentifier(Model $model): ?string
    {
        // Try common identifier fields
        $identifierFields = [
            'name', 'title', 'email', 'username', 'invoice_number', 'tracking_number',
            'order_number', 'po_number', 'quotation_number', 'reference_number', 'grn_number', 'transfer_number',
            'accession_number',
        ];
        
        foreach ($identifierFields as $field) {
            if (isset($model->$field) && !empty($model->$field)) {
                return $model->$field;
            }
        }
        
        // Fallback to ID
        return $model->getKey() ? "ID: {$model->getKey()}" : null;
    }
}
