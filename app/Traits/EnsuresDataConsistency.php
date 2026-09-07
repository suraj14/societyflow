<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait to ensure data consistency in create/update/delete operations
 * Prevents stale data, ensures proper transaction handling, and validates persistence
 */
trait EnsuresDataConsistency
{
    /**
     * Ensure model is saved and persisted to database
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function ensureModelPersisted($model, array $data)
    {
        try {
            // Update model with data
            $model->update($data);
            
            // Force refresh from database to ensure persistence
            $model->refresh();
            
            // Verify data was actually saved
            foreach ($data as $key => $value) {
                if ($model->getAttribute($key) != $value) {
                    Log::warning('Data persistence verification failed', [
                        'model' => get_class($model),
                        'id' => $model->id,
                        'field' => $key,
                        'expected' => $value,
                        'actual' => $model->getAttribute($key)
                    ]);
                }
            }
            
            return $model;
        } catch (\Exception $e) {
            Log::error('Model persistence failed', [
                'model' => get_class($model),
                'id' => $model->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute operation within transaction with verification
     * 
     * @param callable $callback
     * @param string $operationType
     * @return mixed
     */
    protected function executeWithVerification(callable $callback, $operationType = 'operation')
    {
        try {
            DB::beginTransaction();
            
            $result = $callback();
            
            DB::commit();
            
            Log::info("Data consistency: {$operationType} completed successfully");
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Data consistency: {$operationType} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Clear any model caching after update
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function clearModelCache($model)
    {
        // Clear query cache
        if (method_exists($model, 'flushQueryCache')) {
            $model->flushQueryCache();
        }
        
        // Clear any application cache related to this model
        $cacheKey = 'model_' . get_class($model) . '_' . $model->id;
        cache()->forget($cacheKey);
        
        Log::debug('Model cache cleared', [
            'model' => get_class($model),
            'id' => $model->id
        ]);
    }

    /**
     * Verify model exists in database
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function verifyModelExists($model)
    {
        $exists = $model::where('id', $model->id)->exists();
        
        if (!$exists) {
            Log::error('Model verification failed - model not found in database', [
                'model' => get_class($model),
                'id' => $model->id
            ]);
        }
        
        return $exists;
    }

    /**
     * Get fresh model instance from database
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getFreshModel($model)
    {
        return $model::find($model->id);
    }
}
