<?php

namespace App\Traits;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::updated(function ($model) {
            $changes = [
                'before' => array_intersect_key($model->getOriginal(), $model->getChanges()),
                'after' => $model->getChanges(),
            ];

            Log::create([
                'user_id' => Auth::id(),
                'action' => 'update_' . strtolower(class_basename($model)),
                'entity_type' => class_basename($model),
                'entity_id' => $model->id,
                'changes' => $changes,
            ]);
        });
    }
}