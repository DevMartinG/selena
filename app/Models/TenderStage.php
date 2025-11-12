<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tender_id',
        'stage_type',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relación con el tender
     */
    public function tender()
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * Relaciones con las etapas específicas
     */
    public function s1Stage()
    {
        return $this->hasOne(TenderStageS1::class);
    }

    public function s2Stage()
    {
        return $this->hasOne(TenderStageS2::class);
    }

    public function s3Stage()
    {
        return $this->hasOne(TenderStageS3::class);
    }

    public function s4Stage()
    {
        return $this->hasOne(TenderStageS4::class);
    }

    /**
     * Scope para filtrar por tipo de etapa
     */
    public function scopeOfType($query, $stageType)
    {
        return $query->where('stage_type', $stageType);
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Verifica si la etapa está completada
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Verifica si la etapa está en progreso
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Verifica si la etapa está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
