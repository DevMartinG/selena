<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🎯 MODELO: TENDERCUSTOMDEADLINERULE
 *
 * Este modelo maneja las reglas personalizadas de plazos por Tender.
 * Permite que los usuarios definan fechas personalizadas con evidencia
 * que sobrescriben las reglas globales.
 *
 * FUNCIONALIDADES:
 * - Reglas personalizadas por Tender y campo específico
 * - Fecha personalizada definida por el usuario
 * - Evidencia obligatoria (imagen captura) y opcional (PDF completo)
 * - Prioridad sobre reglas globales
 *
 * RELACIONES:
 * - belongsTo Tender
 * - belongsTo User (created_by)
 */
class TenderCustomDeadlineRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tender_id',
        'stage_type',
        'field_name',
        'from_stage',
        'from_field',
        'custom_date',
        'evidence_image',
        'evidence_pdf',
        'description',
        'created_by',
    ];

    protected $casts = [
        'custom_date' => 'date',
    ];

    /**
     * 🎯 Relación con Tender
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * 🎯 Relación con el usuario que creó la regla
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🎯 Scope para reglas por Tender
     */
    public function scopeForTender($query, $tenderId)
    {
        return $query->where('tender_id', $tenderId);
    }

    /**
     * 🎯 Scope para reglas por etapa y campo
     */
    public function scopeForField($query, string $stageType, string $fieldName)
    {
        return $query->where('stage_type', $stageType)
            ->where('field_name', $fieldName);
    }

    /**
     * 🎯 Obtener regla personalizada para un Tender y campo específico
     */
    public static function getCustomRule($tenderId, string $stageType, string $fieldName): ?self
    {
        return self::forTender($tenderId)
            ->forField($stageType, $fieldName)
            ->first();
    }

    /**
     * 🎯 Verificar si existe regla personalizada para un campo
     */
    public static function hasCustomRule($tenderId, string $stageType, string $fieldName): bool
    {
        return self::forTender($tenderId)
            ->forField($stageType, $fieldName)
            ->exists();
    }
}
