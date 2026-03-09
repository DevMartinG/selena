<?php

namespace App\Models;

/**
 * 🎯 MODELO PARA ETAPA S2 - PROCEDIMIENTO DE SELECCIÓN
 *
 * Extiende de TenderStageBase para heredar funcionalidad común
 * y eliminar duplicación de código.
 */
class TenderStageS2 extends TenderStageBase
{
    protected $table = 'tender_stage_s2_selection_process';

    protected $fillable = [
        'tender_stage_id',
        'published_at',
        'participants_registration',
        'restarted_from',
        'cui_code',
        'formulation_obs', // fecha de formulación de consultas y observaciones
        'absolution_obs',
        'base_integration',
        'offer_presentation',
        'offer_evaluation',
        'award_granted_at',
        'award_consent',
        'appeal_date',
        'awarded_tax_id',
        'awarded_legal_name',
    ];

    protected $casts = [
        'published_at' => 'date',
        'participants_registration' => 'date',
        'formulation_obs' => 'date',
        'absolution_obs' => 'date',
        'base_integration' => 'date',
        'offer_presentation' => 'date',
        'offer_evaluation' => 'date',
        'award_granted_at' => 'date',
        'award_consent' => 'date',
        'appeal_date' => 'date',
    ];

    /**
     * Obtiene el tipo de etapa específico
     */
    public function getStageType(): string
    {
        return 'S2';
    }

    /**
     * Obtiene el nombre del tipo de etapa para mostrar
     */
    public function getStageTypeName(): string
    {
        return 'Procedimiento de Selección';
    }
}
