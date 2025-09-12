<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStageS4 extends Model
{
    use HasFactory;

    protected $fillable = [
        'tender_stage_id',
        'contract_details',
        'contract_signing',
        'contract_vigency_date',
    ];

    /**
     * Relación con la etapa del tender
     */
    public function tenderStage()
    {
        return $this->belongsTo(TenderStage::class);
    }

    /**
     * Relación con el tender a través de la etapa
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
}
