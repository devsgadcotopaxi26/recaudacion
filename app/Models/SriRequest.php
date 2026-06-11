<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SriRequest extends Model
{
    protected $fillable = [
        'placa',
        'endpoint',
        'status_code',
        'duration_ms',
        'success',
        'error_type',
        'error_message',
        'cached',
    ];

    protected $casts = [
        'success' => 'boolean',
        'cached' => 'boolean',
    ];

    /**
     * Scope para requests exitosos
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope para requests fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope para requests de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope para requests de las últimas N horas
     */
    public function scopeLastHours($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope para un endpoint específico
     */
    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }
}
