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

    /**
     * Relación con ProcessType
     */
    public function processTypeRelation()
    {
        return $this->belongsTo(\App\Models\ProcessType::class, 'process_type', 'description_short_type');
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

            // 🔧 NUEVA LÓGICA: Extraer códigos antes de normalizar completamente
            $codeInfo = static::extractCodeInfo($tender->identifier);
            $tender->code_short_type = $codeInfo['code_short_type'];
            $tender->code_type = $codeInfo['code_type'];

            // ✅ MAPEO AUTOMÁTICO DE PROCESS_TYPE
            // Extraer solo el prefijo básico (antes del primer espacio)
            $basicPrefix = Str::of($tender->code_short_type)->before(' ')->upper();
            $processType = \App\Models\ProcessType::where('code_short_type', $basicPrefix)->first();
            if ($processType) {
                $tender->process_type = $processType->description_short_type;
            }

            // 🔧 Limpieza del identificador original (para el resto de campos)
            $cleanIdentifier = static::normalizeIdentifier($tender->identifier);

            // ✅ Extraer año (formato 20XX)
            if (! preg_match('/\b(20\d{2})\b/', $cleanIdentifier, $yearMatch)) {
                throw new \Exception("Could not extract year from identifier: '{$tender->identifier}'");
            }
            $tender->code_year = $yearMatch[1];

            // ✅ Extraer code_sequence
            $beforeYear = explode($tender->code_year, $cleanIdentifier)[0] ?? '';
            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
            $tender->code_sequence = static::extractLastNumeric($segmentsBeforeYear);

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

    /**
     * Extrae code_type y code_short_type desde el identifier antes del primer guión.
     *
     * code_short_type: Solo lo que está antes del primer guión
     * code_type: Lo que está antes del primer guión + el siguiente segmento
     */
    protected static function extractCodeInfo(string $identifier): array
    {
        // 1. Extraer segmento antes del primer guión
        $beforeFirstDash = Str::of($identifier)->before('-');

        // 2. Limpiar espacios inteligentemente
        $cleaned = trim($beforeFirstDash);  // Eliminar espacios inicio/final
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);  // Múltiples espacios → un espacio

        // 3. Normalizar (mayúsculas + sin tildes)
        $upper = mb_strtoupper($cleaned, 'UTF-8');
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper) ?: $upper;

        // 4. Generar code_short_type (solo lo antes del primer guión)
        $codeShortType = $normalized;

        // 5. Generar code_type (antes del primer guión + siguiente segmento)
        $segments = explode('-', $identifier);
        if (count($segments) >= 2) {
            // Tomar el primer segmento + el segundo segmento
            $firstSegment = trim($segments[0]);
            $secondSegment = trim($segments[1]);

            // Limpiar espacios en ambos segmentos
            $firstClean = preg_replace('/\s+/', ' ', trim($firstSegment));
            $secondClean = preg_replace('/\s+/', ' ', trim($secondSegment));

            // Normalizar ambos segmentos
            $firstNormalized = mb_strtoupper($firstClean, 'UTF-8');
            $firstNormalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $firstNormalized) ?: $firstNormalized;

            $secondNormalized = mb_strtoupper($secondClean, 'UTF-8');
            $secondNormalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $secondNormalized) ?: $secondNormalized;

            $codeType = $firstNormalized.'-'.$secondNormalized;
        } else {
            // Si solo hay un segmento, code_type = code_short_type
            $codeType = $normalized;
        }

        return [
            'code_short_type' => $codeShortType,
            'code_type' => $codeType,
        ];
    }
}
