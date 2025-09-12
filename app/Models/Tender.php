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
        // Code fields
        'code_sequence',
        'code_type',
        'code_short_type',
        'code_year',
        'code_attempt',
        'code_full',
        
        // General Info
        'entity_name',
        'process_type',
        'identifier',
        'contract_object',
        'object_description',
        'estimated_referenced_value',
        'currency_name',
        'current_status',
        
        // S1: Actuaciones Preparatorias
        's1_request_presentation_doc',
        's1_request_presentation_date',
        's1_market_indagation_doc',
        's1_market_indagation_date',
        's1_with_certification',
        's1_certification_date',
        's1_no_certification_reason',
        's1_approval_expedient_date',
        's1_selection_committee_date',
        's1_administrative_bases_date',
        's1_approval_expedient_format_2',
        
        // S2: Procedimiento de Selección
        's2_published_at',
        's2_participants_registration',
        's2_restarted_from',
        's2_cui_code',
        's2_absolution_obs',
        's2_base_integration',
        's2_offer_presentation',
        's2_offer_evaluation',
        's2_award_granted_at',
        's2_award_consent',
        's2_appeal_date',
        's2_awarded_tax_id',
        's2_awarded_legal_name',
        
        // S3: Suscripción del Contrato
        's3_doc_sign_presentation_date',
        's3_contract_signing',
        's3_awarded_amount',
        's3_adjusted_amount',
        
        // S4: Tiempo de Ejecución
        's4_contract_details',
        's4_contract_signing',
        's4_contract_vigency_date',
        
        // Datos Adicionales
        'observation',
        'selection_comittee',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        // S1: Actuaciones Preparatorias - Date fields
        's1_request_presentation_date' => 'date',
        's1_market_indagation_date' => 'date',
        's1_certification_date' => 'date',
        's1_approval_expedient_date' => 'date',
        's1_selection_committee_date' => 'date',
        's1_administrative_bases_date' => 'date',
        's1_approval_expedient_format_2' => 'date',
        
        // S2: Procedimiento de Selección - Date fields
        's2_published_at' => 'date',
        's2_participants_registration' => 'date',
        's2_absolution_obs' => 'date',
        's2_base_integration' => 'date',
        's2_offer_presentation' => 'date',
        's2_offer_evaluation' => 'date',
        's2_award_granted_at' => 'date',
        's2_award_consent' => 'date',
        's2_appeal_date' => 'date',
        
        // S3: Suscripción del Contrato - Date fields
        's3_doc_sign_presentation_date' => 'date',
        's3_contract_signing' => 'date',
        
        // Decimal fields
        'estimated_referenced_value' => 'decimal:2',
        's3_awarded_amount' => 'decimal:2',
        's3_adjusted_amount' => 'decimal:2',
        
        // Boolean fields
        's1_with_certification' => 'boolean',
    ];

    /**
     * Boot the model and attach events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Tender $tender) {
            if (empty($tender->identifier)) {
                throw new \Exception('The "identifier" field is required to generate code metadata.');
            }

            // 🔧 Limpieza del identificador original
            $cleanIdentifier = static::normalizeIdentifier($tender->identifier);

            // ✅ Extraer code_short_type (prefijo antes del primer guion)
            $tender->code_short_type = Str::of($cleanIdentifier)->before('-')->upper();

            // ✅ Extraer año (formato 20XX)
            if (! preg_match('/\b(20\d{2})\b/', $cleanIdentifier, $yearMatch)) {
                throw new \Exception("Could not extract year from identifier: '{$tender->identifier}'");
            }
            $tender->code_year = $yearMatch[1];

            // ✅ Extraer code_sequence
            $beforeYear = explode($tender->code_year, $cleanIdentifier)[0] ?? '';
            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
            $tender->code_sequence = static::extractLastNumeric($segmentsBeforeYear);

            // ✅ Extraer code_type
            $sequenceIndex = array_search($tender->code_sequence, $segmentsBeforeYear);
            $typeSegments = array_slice($segmentsBeforeYear, 0, $sequenceIndex);
            $tender->code_type = str_replace(' ', '', implode('-', $typeSegments));

            // ✅ Extraer attempt (último número en todo el string)
            preg_match_all('/\d+/', $cleanIdentifier, $allNumbers);
            $tender->code_attempt = $allNumbers[0] ? (int) end($allNumbers[0]) : 1;

            // ✅ Establecer code_full normalizado (usado para evitar duplicados)
            $tender->code_full = $cleanIdentifier;

            // ❌ Verificar duplicado por code_full
            /* if (Tender::where('code_full', $tender->code_full)->exists()) {
                throw new \Exception("Duplicated process: '{$tender->code_full}' already exists.");
            } */
        });
    }

    /**
     * Limpia y normaliza un identifier: sin espacios, mayúsculas y sin tildes.
     */
    protected static function normalizeIdentifier(string $identifier): string
    {
        $noSpaces = preg_replace('/\s+/', '', $identifier);
        $upper = mb_strtoupper($noSpaces, 'UTF-8');

        // Elimina tildes usando transliteración (UTF-8 safe)
        $noAccents = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper);

        // Fallback si iconv falla
        return $noAccents ?: $upper;
    }

    /**
     * Extrae el último número desde una lista de segmentos.
     */
    protected static function extractLastNumeric(array $segments): int
    {
        foreach (array_reverse($segments) as $segment) {
            if (is_numeric($segment)) {
                return (int) $segment;
            }
        }

        throw new \Exception('Could not extract code_sequence: no numeric segment found.');
    }
}
