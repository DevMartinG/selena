<?php

namespace App\Models;

/**
 * 🎯 MODELO PARA ETAPA S3 - SUSCRIPCIÓN DEL CONTRATO
 * 
 * Extiende de TenderStageBase para heredar funcionalidad común
 * y eliminar duplicación de código.
 */
class TenderStageS3 extends TenderStageBase
{
    protected $table = 'tender_stage_s3_contract_signing';

    protected $fillable = [
        'tender_stage_id',
        'doc_sign_presentation_date',
        'contract_signing',
        'awarded_amount',
        'adjusted_amount',
    ];

    protected $casts = [
        'doc_sign_presentation_date' => 'date',
        'contract_signing' => 'date',
        'awarded_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
    ];

    /**
     * Obtiene el tipo de etapa específico
     */
    public function getStageType(): string
    {
        return 'S3';
    }

    /**
     * Obtiene el nombre del tipo de etapa para mostrar
     */
    public function getStageTypeName(): string
    {
        return 'Suscripción del Contrato';
    }
}
