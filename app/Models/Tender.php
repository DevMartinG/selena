<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'code',
        'sequence_number',
        'entity_name',
        'published_at',
        'identifier',
        'restarted_from',
        'contract_object',
        'object_description',
        'cui_code',
        'estimated_referenced_value',
        'currency_name',
        'absolution_obs',
        'offer_presentation',
        'award_granted_at',
        'award_consent',
        'current_status',
        'awarded_tax_id',
        'awarded_legal_name',
        'awarded_amount',
        'contract_signing',
        'adjusted_amount',
        'observation',
        'selection_comittee',
        'contract_execution',
        'contract_details',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        'published_date' => 'date',
        'restarted_from' => 'date',
        'absolution_obs' => 'date',
        'offer_presentation' => 'date',
        'award_granted_at' => 'date',
        'award_consent' => 'date',
        'contract_signing' => 'date',

        'estimated_referenced_value' => 'decimal:2',
        'awarded_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
    ];
}
