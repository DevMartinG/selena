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

        // Datos Adicionales
        'observation',
        'selection_comittee',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        'estimated_referenced_value' => 'decimal:2',
    ];

    /**
     * Relaciones con las etapas del proceso
     */
    public function stages()
    {
        return $this->hasMany(TenderStage::class);
    }

    public function s1Stage()
    {
        return $this->hasOneThrough(
            TenderStageS1::class,
            TenderStage::class,
            'tender_id',
            'tender_stage_id',
            'id',
            'id'
        )->where('tender_stages.stage_type', 'S1');
    }

    public function s2Stage()
    {
        return $this->hasOneThrough(
            TenderStageS2::class,
            TenderStage::class,
            'tender_id',
            'tender_stage_id',
            'id',
            'id'
        )->where('tender_stages.stage_type', 'S2');
    }

    public function s3Stage()
    {
        return $this->hasOneThrough(
            TenderStageS3::class,
            TenderStage::class,
            'tender_id',
            'tender_stage_id',
            'id',
            'id'
        )->where('tender_stages.stage_type', 'S3');
    }

    public function s4Stage()
    {
        return $this->hasOneThrough(
            TenderStageS4::class,
            TenderStage::class,
            'tender_id',
            'tender_stage_id',
            'id',
            'id'
        )->where('tender_stages.stage_type', 'S4');
    }

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
