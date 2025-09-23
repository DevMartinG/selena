<?php

namespace App\Models;

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
        'stage_type',
        'from_field',
        'to_field',
        'legal_days',
        'is_active',
        'is_mandatory',
        'description',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
        'legal_days' => 'integer',
    ];

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
     * 🎯 Scope para reglas por etapa
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage_type', $stage);
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
        return self::active()->orderBy('stage_type')->orderBy('from_field')->get();
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
     * 🎯 Obtener opciones de campos por etapa
     */
    public static function getFieldOptionsByStage(string $stage): array
    {
        $fields = [
            'S1' => [
                'request_presentation_date' => 'Presentación de Requerimiento',
                'market_indagation_date' => 'Indagación de Mercado',
                'certification_date' => 'Certificación',
                'approval_expedient_date' => 'Aprobación del Expediente',
                'selection_committee_date' => 'Designación del Comité',
                'administrative_bases_date' => 'Elaboración de Bases Administrativas',
                'approval_expedient_format_2' => 'Aprobación de Bases Administrativas Formato 2',
            ],
            'S2' => [
                'published_at' => 'Registro de Convocatoria en el SEACE',
                'participants_registration' => 'Registro de Participantes',
                'absolution_obs' => 'Absolución de Consultas y Observaciones',
                'base_integration' => 'Integración de las Bases',
                'offer_presentation' => 'Presentación de Propuestas',
                'offer_evaluation' => 'Calificación y Evaluación de Propuestas',
                'award_granted_at' => 'Otorgamiento de Buena Pro',
                'award_consent' => 'Consentimiento de Buena Pro',
                'appeal_date' => 'Apelación',
            ],
            'S3' => [
                'doc_sign_presentation_date' => 'Presentación de Documentos de Suscripción',
                'contract_signing' => 'Suscripción del Contrato',
            ],
            'S4' => [
                'contract_signing' => 'Fecha de Suscripción del Contrato',
                'contract_vigency_date' => 'Fecha de Vigencia del Contrato',
            ],
        ];

        return $fields[$stage] ?? [];
    }

    /**
     * 🎯 Obtener descripción legible de la regla
     */
    public function getReadableDescription(): string
    {
        $stageOptions = self::getStageOptions();
        $fieldOptions = self::getFieldOptionsByStage($this->stage_type);
        
        $stageName = $stageOptions[$this->stage_type] ?? $this->stage_type;
        $fromName = $fieldOptions[$this->from_field] ?? $this->from_field;
        $toName = $fieldOptions[$this->to_field] ?? $this->to_field;
        
        return "{$stageName}: {$fromName} → {$toName} ({$this->legal_days} días hábiles)";
    }

    /**
     * 🎯 Verificar si la regla está configurada correctamente
     */
    public function isValid(): bool
    {
        return !empty($this->stage_type) && 
               !empty($this->from_field) && 
               !empty($this->to_field) && 
               $this->legal_days > 0;
    }
}
