<?php

namespace App\Models;

use App\Traits\HasStageMutators;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tender extends Model
{
    use HasFactory, HasStageMutators;

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
        'tender_status_id',
        'seace_tender_id',

        // Datos Adicionales
        'observation',
        'selection_comittee',
        'with_identifier',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        'estimated_referenced_value' => 'decimal:2',
        'with_identifier' => 'boolean',
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

    /**
     * Relación con TenderStatus
     */
    public function tenderStatus()
    {
        return $this->belongsTo(TenderStatus::class, 'tender_status_id');
    }

    /**
     * Relación con SeaceTender (procedimiento origen)
     */
    public function seaceTender()
    {
        return $this->belongsTo(\App\Models\SeaceTender::class, 'seace_tender_id');
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
            // Si with_identifier es false, generar identifier automático
            if (!$tender->with_identifier || empty($tender->identifier) || str_starts_with($tender->identifier, 'TEMP-GENERATED-')) {
                $tender->identifier = static::generateAutomaticIdentifier();
                $tender->with_identifier = false; // Marcar como sin nomenclatura válida
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
            } else {
                // Si no se encuentra el process_type, usar uno por defecto
                $tender->process_type = 'Sin Clasificar';
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
            $attempt = $allNumbers[0] ? (int) end($allNumbers[0]) : 1;
            $tender->code_attempt = min($attempt, 255); // Limitar a unsignedTinyInteger

            // ✅ Establecer code_full normalizado (usado para evitar duplicados)
            $tender->code_full = $cleanIdentifier;

            // ❌ Verificar duplicado por code_full
            /* if (Tender::where('code_full', $tender->code_full)->exists()) {
                throw new \Exception("Duplicated process: '{$tender->code_full}' already exists.");
            } */
        });

        static::updating(function (Tender $tender) {
            // Si cambió el identifier, regenerar campos derivados
            if ($tender->isDirty('identifier')) {
                // Si el nuevo identifier viene de SEACE (no es temporal), regenerar campos
                if (!$tender->identifier || str_starts_with($tender->identifier, 'TEMP-GENERATED-')) {
                    // Mantener identifier temporal, no regenerar campos derivados
                    return;
                }
                
                // Regenerar todos los campos derivados
                static::regenerateCodeFields($tender);
                
                // Verificar duplicados
                $normalized = static::normalizeIdentifier($tender->identifier);
                $existingTender = Tender::where('code_full', $normalized)
                    ->where('id', '!=', $tender->id)
                    ->first();
                    
                if ($existingTender) {
                    throw new \Exception("Ya existe un procedimiento con la nomenclatura: '{$tender->identifier}'");
                }
            }
        });
    }

    // ========================================================================
    // 🎯 MÉTODOS DE UTILIDAD PARA GENERACIÓN DE CÓDIGOS
    // ========================================================================

    /**
     * Normaliza un identificador eliminando espacios extra y caracteres especiales
     */
    public static function normalizeIdentifier(string $identifier): string
    {
        // Eliminar espacios extra y normalizar
        $normalized = preg_replace('/\s+/', ' ', trim($identifier));

        // Convertir a mayúsculas
        $upper = mb_strtoupper($normalized, 'UTF-8');

        // Normalizar caracteres especiales (quitar acentos)
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper) ?: $upper;

        return $clean;
    }

    /**
     * Extrae información de códigos del identificador
     *
     * @param  string  $identifier  El identificador original
     * @return array Array con 'code_short_type' y 'code_type'
     */
    protected static function extractCodeInfo(string $identifier): array
    {
        // Extraer la parte antes del primer guión
        $beforeFirstDash = Str::of($identifier)->before('-');
        $cleaned = trim($beforeFirstDash);

        // Limpiar espacios extra pero mantener estructura
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        // Convertir a mayúsculas
        $upper = mb_strtoupper($cleaned, 'UTF-8');

        // Normalizar caracteres especiales (quitar acentos)
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper) ?: $upper;

        $codeShortType = $normalized;

        // Para code_type, necesitamos el primer segmento + el segundo segmento
        $segments = explode('-', $identifier);
        if (count($segments) >= 2) {
            $firstSegment = trim($segments[0]);
            $secondSegment = trim($segments[1]);

            // Limpiar espacios extra en cada segmento
            $firstClean = preg_replace('/\s+/', ' ', trim($firstSegment));
            $secondClean = preg_replace('/\s+/', ' ', trim($secondSegment));

            // Normalizar cada segmento
            $firstNormalized = mb_strtoupper($firstClean, 'UTF-8');
            $firstNormalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $firstNormalized) ?: $firstNormalized;

            $secondNormalized = mb_strtoupper($secondClean, 'UTF-8');
            $secondNormalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $secondNormalized) ?: $secondNormalized;

            $codeType = $firstNormalized.'-'.$secondNormalized;
        } else {
            $codeType = $normalized;
        }

        return [
            'code_short_type' => $codeShortType,
            'code_type' => $codeType,
        ];
    }

    /**
     * Extrae el último número de un array de segmentos
     */
    protected static function extractLastNumeric(array $segments): int
    {
        $numbers = [];
        foreach ($segments as $segment) {
            if (preg_match('/\d+/', $segment, $matches)) {
                $numbers[] = (int) $matches[0];
            }
        }

        return $numbers ? end($numbers) : 0;
    }

    /**
     * Obtiene el ID del estado por defecto para importaciones Excel
     */
    public static function getDefaultTenderStatusId(): ?int
    {
        return TenderStatus::where('code', '--')->value('id');
    }

    /**
     * Genera un identifier automático para Tenders sin nomenclatura válida
     * Formato: SIN-NOMENCLATURA-YYYY-MM-DD-HHMMSS-XXX
     */
    public static function generateAutomaticIdentifier(): string
    {
        $timestamp = now()->format('Y-m-d-His');
        $random = rand(100, 999);
        return "SIN CODIGO-NOMENCLATURA-{$timestamp}-{$random}";
    }

    /**
     * Regenera todos los campos derivados del identifier
     */
    public static function regenerateCodeFields(Tender $tender): void
    {
        $codeInfo = static::extractCodeInfo($tender->identifier);
        $tender->code_short_type = $codeInfo['code_short_type'];
        $tender->code_type = $codeInfo['code_type'];

        $cleanIdentifier = static::normalizeIdentifier($tender->identifier);

        if (preg_match('/\b(20\d{2})\b/', $cleanIdentifier, $yearMatch)) {
            $tender->code_year = $yearMatch[1];

            $beforeYear = explode($tender->code_year, $cleanIdentifier)[0] ?? '';
            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
            $tender->code_sequence = static::extractLastNumeric($segmentsBeforeYear);

            preg_match_all('/\d+/', $cleanIdentifier, $allNumbers);
            $attempt = $allNumbers[0] ? (int) end($allNumbers[0]) : 1;
            $tender->code_attempt = min($attempt, 255); // Limitar a unsignedTinyInteger

            $tender->code_full = $cleanIdentifier;

            // Actualizar process_type
            $basicPrefix = Str::of($tender->code_short_type)->before(' ')->upper();
            $processType = \App\Models\ProcessType::where('code_short_type', $basicPrefix)->first();
            if ($processType) {
                $tender->process_type = $processType->description_short_type;
            } else {
                $tender->process_type = 'Sin Clasificar';
            }
        }
    }
}
