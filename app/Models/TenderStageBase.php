<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 🎯 MODELO BASE PARA STAGES DE TENDER
 * 
 * Este modelo abstracto contiene la lógica común para todos los stages
 * específicos (S1, S2, S3, S4), eliminando duplicación de código.
 * 
 * Características:
 * - Relaciones comunes (tenderStage, tender)
 * - Lógica de validación compartida
 * - Métodos de utilidad comunes
 */
abstract class TenderStageBase extends Model
{
    use HasFactory;

    /**
     * Campos comunes a todos los stages
     */
    protected $fillable = [
        'tender_stage_id',
    ];

    /**
     * Relación con la etapa del tender (común a todos)
     */
    public function tenderStage()
    {
        return $this->belongsTo(TenderStage::class);
    }

    /**
     * Relación con el tender a través de la etapa (común a todos)
     */
    public function tender()
    {
        return $this->hasOneThrough(
            Tender::class,
            TenderStage::class,
            'id',
            'id',
            'tender_stage_id',
            'tender_id'
        );
    }

    /**
     * Scope para filtrar por tipo de etapa
     */
    public function scopeOfStageType($query, string $stageType)
    {
        return $query->whereHas('tenderStage', function ($q) use ($stageType) {
            $q->where('stage_type', $stageType);
        });
    }

    /**
     * Scope para filtrar por estado de etapa
     */
    public function scopeWithStageStatus($query, string $status)
    {
        return $query->whereHas('tenderStage', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    /**
     * Verifica si la etapa está completada
     */
    public function isStageCompleted(): bool
    {
        return $this->tenderStage?->isCompleted() ?? false;
    }

    /**
     * Verifica si la etapa está en progreso
     */
    public function isStageInProgress(): bool
    {
        return $this->tenderStage?->isInProgress() ?? false;
    }

    /**
     * Verifica si la etapa está pendiente
     */
    public function isStagePending(): bool
    {
        return $this->tenderStage?->isPending() ?? false;
    }

    /**
     * Obtiene el tipo de etapa
     */
    public function getStageType(): ?string
    {
        return $this->tenderStage?->stage_type;
    }

    /**
     * Obtiene el estado de la etapa
     */
    public function getStageStatus(): ?string
    {
        return $this->tenderStage?->status;
    }

    /**
     * Marca la etapa como completada
     */
    public function markAsCompleted(): bool
    {
        if (!$this->tenderStage) {
            return false;
        }

        return $this->tenderStage->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Marca la etapa como en progreso
     */
    public function markAsInProgress(): bool
    {
        if (!$this->tenderStage) {
            return false;
        }

        return $this->tenderStage->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Obtiene el nombre del tipo de etapa para mostrar
     */
    public function getStageTypeName(): string
    {
        return match ($this->getStageType()) {
            'S1' => 'Actuaciones Preparatorias',
            'S2' => 'Procedimiento de Selección',
            'S3' => 'Suscripción del Contrato',
            'S4' => 'Tiempo de Ejecución',
            default => 'Etapa Desconocida',
        };
    }

    /**
     * Obtiene el nombre del estado para mostrar
     */
    public function getStageStatusName(): string
    {
        return match ($this->getStageStatus()) {
            'pending' => 'Pendiente',
            'in_progress' => 'En Progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            default => 'Estado Desconocido',
        };
    }
}
