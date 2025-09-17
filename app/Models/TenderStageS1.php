<?php

namespace App\Models;

/**
 * 🎯 MODELO PARA ETAPA S1 - ACTUACIONES PREPARATORIAS
 * 
 * Extiende de TenderStageBase para heredar funcionalidad común
 * y eliminar duplicación de código.
 */
class TenderStageS1 extends TenderStageBase
{
    protected $table = 'tender_stage_s1_preparatory_actions';

    protected $fillable = [
        'tender_stage_id',
        'request_presentation_doc',
        'request_presentation_date',
        'market_indagation_doc',
        'market_indagation_date',
        'with_certification',
        'certification_date',
        'no_certification_reason',
        'approval_expedient_date',
        'selection_committee_date',
        'administrative_bases_date',
        'approval_expedient_format_2',
    ];

    protected $casts = [
        'request_presentation_date' => 'date',
        'market_indagation_date' => 'date',
        'certification_date' => 'date',
        'approval_expedient_date' => 'date',
        'selection_committee_date' => 'date',
        'administrative_bases_date' => 'date',
        'approval_expedient_format_2' => 'date',
        'with_certification' => 'boolean',
    ];

    /**
     * Obtiene el tipo de etapa específico
     */
    public function getStageType(): string
    {
        return 'S1';
    }

    /**
     * Obtiene el nombre del tipo de etapa para mostrar
     */
    public function getStageTypeName(): string
    {
        return 'Actuaciones Preparatorias';
    }
}
