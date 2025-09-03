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
