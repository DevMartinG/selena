<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tender extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'code_sequence',
        'code_type',
        'code_short_type',
        'code_year',
        'code_full',
        'code_attempt',
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
        'absolution_obs' => 'date',
        'offer_presentation' => 'date',
        'award_granted_at' => 'date',
        'award_consent' => 'date',
        'contract_signing' => 'date',

        'estimated_referenced_value' => 'decimal:2',
        'awarded_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
    ];

    /**
     * Boot the model and attach events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Tender $tender) {
            if (empty($tender->identifier)) {
                throw new \Exception('Identifier is required to generate code fields.');
            }

            // Clean identifier (remove ALL whitespace)
            $cleanedIdentifier = preg_replace('/\s+/', '', $tender->identifier);

            // Extract code_short_type (prefix before first hyphen)
            $codeShortType = Str::of($cleanedIdentifier)->before('-')->upper();
            $tender->code_short_type = $codeShortType;

            // Extract year
            if (! preg_match('/\b(20\d{2})\b/', $cleanedIdentifier, $yearMatch)) {
                throw new \Exception('Could not extract year from identifier.');
            }

            $year = $yearMatch[1];
            $parts = explode($year, $cleanedIdentifier);
            $beforeYear = trim($parts[0] ?? '');

            // Extract code_sequence
            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
            $codeSequence = null;

            foreach (array_reverse($segmentsBeforeYear) as $segment) {
                if (is_numeric($segment)) {
                    $codeSequence = (int) $segment;
                    break;
                }
            }

            if (is_null($codeSequence)) {
                throw new \Exception('Could not extract code sequence from identifier.');
            }

            // Extract code_type
            $sequenceIndex = array_search($codeSequence, $segmentsBeforeYear);
            $typeSegments = array_slice($segmentsBeforeYear, 0, $sequenceIndex);
            $rawCodeType = trim(implode('-', $typeSegments));

            if (empty($rawCodeType)) {
                throw new \Exception('Could not extract code type from identifier.');
            }

            $normalizedCodeType = str_replace(' ', '', $rawCodeType);

            // Extract code_attempt using regex (safe)
            preg_match_all('/\d+/', $cleanedIdentifier, $numbers);
            $lastNumber = $numbers[0] ? end($numbers[0]) : null;
            $codeAttempt = $lastNumber ? (int) $lastNumber : 1;

            // Set fields
            $tender->code_sequence = $codeSequence;
            $tender->code_type = $normalizedCodeType;
            $tender->code_year = $year;
            $tender->code_attempt = $codeAttempt;
            $tender->code_full = "{$codeSequence}-{$normalizedCodeType}-{$codeAttempt}";

            // Check for uniqueness
            if (Tender::where('code_full', $tender->code_full)->exists()) {
                throw new \Exception("Duplicated process: '{$tender->code_full}' already exists.");
            }

            // Debug (solo si quieres seguir viendo valores)
            /*
            dd([
                'original_identifier' => $tender->identifier,
                'cleaned_identifier' => $cleanedIdentifier,
                'code_sequence' => $codeSequence,
                'raw_code_type' => $rawCodeType,
                'normalized_code_type' => $normalizedCodeType,
                'code_attempt' => $codeAttempt,
                'code_full' => $tender->code_full,
            ]);
            */
        });
    }
}
