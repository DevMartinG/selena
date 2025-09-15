<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStageS3 extends Model
{
    use HasFactory;

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
