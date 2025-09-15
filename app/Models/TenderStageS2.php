<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStageS2 extends Model
{
    use HasFactory;

    protected $table = 'tender_stage_s2_selection_process';

    protected $fillable = [
        'tender_stage_id',
        'published_at',
        'participants_registration',
        'restarted_from',
        'cui_code',
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
        'absolution_obs' => 'date',
        'base_integration' => 'date',
        'offer_presentation' => 'date',
        'offer_evaluation' => 'date',
        'award_granted_at' => 'date',
        'award_consent' => 'date',
        'appeal_date' => 'date',
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
