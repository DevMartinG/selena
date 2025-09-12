<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStageS1 extends Model
{
    use HasFactory;

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
