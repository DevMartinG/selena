<?php

namespace App\Models;

use App\Services\TenderFieldExtractor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🎯 MODELO: TENDERDEADLINERULE
 *
 * Este modelo maneja las reglas de plazos legales para los procesos de selección.
 * Define los plazos permitidos entre diferentes campos de fecha en cada etapa.
 *
 * FUNCIONALIDADES:
 * - Configuración de plazos por etapa (S1, S2, S3, S4)
 * - Validación de días hábiles permitidos
 * - Control de reglas activas/inactivas
 * - Reglas obligatorias/opcionales
 * - Auditoría de creación
 *
 * RELACIONES:
 * - belongsTo User (created_by)
 *
 * SCOPES:
 * - active(): Reglas activas
 * - mandatory(): Reglas obligatorias
 * - byStage(): Reglas por etapa
 */
class TenderDeadlineRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_stage',
        'to_stage',
        'from_field',
        'to_field',
        'legal_days',
        'is_active',
        'is_mandatory',
        'description',
        'created_by',
        'process_type_id', // nuevo
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
        'legal_days' => 'integer',
    ];

    /**
     * 🎯 Relación con ProcessType
     */
    public function processType(): BelongsTo
    {
        return $this->belongsTo(ProcessType::class, 'process_type_id');
    }


    /**
     * 🎯 Relación con el usuario que creó la regla
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🎯 Scope para reglas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 Scope para reglas obligatorias
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * 🎯 Scope para reglas por etapa (busca en from_stage o to_stage)
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where(function ($q) use ($stage) {
            $q->where('from_stage', $stage)
              ->orWhere('to_stage', $stage);
        });
    }

    /**
     * 🎯 Scope para reglas por etapa origen
     */
    public function scopeByFromStage($query, string $stage)
    {
        return $query->where('from_stage', $stage);
    }

    /**
     * 🎯 Scope para reglas por etapa destino
     */
    public function scopeByToStage($query, string $stage)
    {
        return $query->where('to_stage', $stage);
    }

    /**
     * 🎯 Scope para reglas activas y obligatorias
     */
    public function scopeActiveMandatory($query)
    {
        return $query->active()->mandatory();
    }

    /**
     * 🎯 Obtener reglas activas por etapa
     */
    public static function getActiveRulesByStage(string $stage): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->byStage($stage)->get();
    }

    /**
     * 🎯 Obtener todas las reglas activas
     */
    public static function getAllActiveRules(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->orderBy('from_stage')->orderBy('from_field')->get();
    }

    /**
     * 🎯 Verificar si una regla existe para un par de campos
     */
    public static function existsForFields(string $fromField, string $toField): bool
    {
        return self::where('from_field', $fromField)
            ->where('to_field', $toField)
            ->active()
            ->exists();
    }

    /**
     * 🎯 Obtener regla para un par de campos específico
     */
    public static function getRuleForFields(string $fromField, string $toField): ?self
    {
        return self::where('from_field', $fromField)
            ->where('to_field', $toField)
            ->active()
            ->first();
    }

    /**
     * 🎯 Obtener opciones de etapas para formularios
     */
    public static function getStageOptions(): array
    {
        return [
            'S1' => 'E1 - Actuaciones Preparatorias',
            'S2' => 'E2 - Proceso de Selección',
            'S3' => 'E3 - Suscripción del Contrato',
            'S4' => 'E4 - Tiempo de Ejecución',
        ];
    }

    /**
     * 🎯 Obtener opciones de campos por etapa (DINÁMICO)
     *
     * Este método ahora usa TenderFieldExtractor para obtener
     * dinámicamente los campos de fecha de cada etapa desde
     * los componentes de Filament, evitando hardcoding.
     */
    public static function getFieldOptionsByStage(string $stage): array
    {
        return TenderFieldExtractor::getFieldOptionsByStage($stage);
    }

    /**
     * 🎯 Obtener descripción legible de la regla
     */
    public function getReadableDescription(): string
    {
        $stageOptions = self::getStageOptions();
        $fromStageOptions = self::getFieldOptionsByStage($this->from_stage);
        $toStageOptions = self::getFieldOptionsByStage($this->to_stage);

        $fromStageName = $stageOptions[$this->from_stage] ?? $this->from_stage;
        $toStageName = $stageOptions[$this->to_stage] ?? $this->to_stage;
        $fromName = $fromStageOptions[$this->from_field] ?? $this->from_field;
        $toName = $toStageOptions[$this->to_field] ?? $this->to_field;

        // Si las etapas son diferentes, mostrar ambas
        if ($this->from_stage !== $this->to_stage) {
            return "{$fromStageName} ({$fromName}) → {$toStageName} ({$toName}) ({$this->legal_days} días hábiles)";
        }

        return "{$fromStageName}: {$fromName} → {$toName} ({$this->legal_days} días hábiles)";
    }

    /**
     * 🎯 Verificar si la regla está configurada correctamente
     */
    public function isValid(): bool
    {
        return ! empty($this->from_stage) &&
               ! empty($this->to_stage) &&
               ! empty($this->from_field) &&
               ! empty($this->to_field) &&
               $this->legal_days > 0;
    }

    /**
     * 🎯 Verificar si los campos de la regla existen dinámicamente
     */
    public function fieldsExist(): bool
    {
        $fromExists = TenderFieldExtractor::fieldExistsInStage($this->from_stage, $this->from_field);
        $toExists = TenderFieldExtractor::fieldExistsInStage($this->to_stage, $this->to_field);

        return $fromExists && $toExists;
    }

    /**
     * 🎯 Obtener información de los campos de la regla
     */
    public function getFieldsInfo(): array
    {
        return [
            'from_field' => TenderFieldExtractor::getFieldInfo($this->from_stage, $this->from_field),
            'to_field' => TenderFieldExtractor::getFieldInfo($this->to_stage, $this->to_field),
        ];
    }

    /**
     * 🎯 Obtener estadísticas de todas las etapas
     */
    public static function getStagesStatistics(): array
    {
        return TenderFieldExtractor::getStageStatistics();
    }

    /**
     * 🎯 Verificar si una etapa tiene campos disponibles
     */
    public static function stageHasFields(string $stage): bool
    {
        $fields = TenderFieldExtractor::getFieldOptionsByStage($stage);

        return ! empty($fields);
    }

    /**
     * 🎯 Obtener etapas disponibles con campos
     */
    public static function getAvailableStagesWithFields(): array
    {
        $stages = [];
        $stageOptions = self::getStageOptions();

        foreach ($stageOptions as $stageCode => $stageName) {
            if (self::stageHasFields($stageCode)) {
                $stages[$stageCode] = $stageName;
            }
        }

        return $stages;
    }
}
