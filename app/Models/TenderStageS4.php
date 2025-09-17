<?php

namespace App\Models;

/**
 * 🎯 MODELO PARA ETAPA S4 - TIEMPO DE EJECUCIÓN
 *
 * Extiende de TenderStageBase para heredar funcionalidad común
 * y eliminar duplicación de código.
 */
class TenderStageS4 extends TenderStageBase
{
    protected $table = 'tender_stage_s4_execution_time';

    protected $fillable = [
        'tender_stage_id',
        'contract_details',
        'contract_signing',
        'contract_vigency_date',
    ];

    protected $casts = [
        'contract_signing' => 'date',
        'contract_vigency_date' => 'date',
    ];

    /**
     * Obtiene el tipo de etapa específico
     */
    public function getStageType(): string
    {
        return 'S4';
    }

    /**
     * Obtiene el nombre del tipo de etapa para mostrar
     */
    public function getStageTypeName(): string
    {
        return 'Tiempo de Ejecución';
    }
}
