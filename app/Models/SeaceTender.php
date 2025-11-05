<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeaceTender extends Model
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
        'base_code', // Proceso base para agrupar intentos

        // General Info
        'entity_name',
        'process_type',
        'identifier',
        'contract_object',
        'object_description',
        'estimated_referenced_value',
        'currency_name',
        'tender_status_id',

        // Datos Adicionales - MODIFICADOS para SeaceTender
        'publish_date',
        'publish_date_time',
        'resumed_from',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        'estimated_referenced_value' => 'decimal:2',
        'publish_date' => 'date',
    ];

    /**
     * Relación con TenderStatus
     */
    public function tenderStatus()
    {
        return $this->belongsTo(TenderStatus::class, 'tender_status_id');
    }

    /**
     * Relación con ProcessType
     */
    public function processTypeRelation()
    {
        return $this->belongsTo(\App\Models\ProcessType::class, 'process_type', 'description_short_type');
    }

    /**
     * Boot the model and attach events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (SeaceTender $seaceTender) {
            if (empty($seaceTender->identifier)) {
                throw new \Exception('The "identifier" field is required to generate code metadata.');
            }

            // 🔧 NUEVA LÓGICA: Extraer códigos antes de normalizar completamente
            $codeInfo = static::extractCodeInfo($seaceTender->identifier);
            $seaceTender->code_short_type = $codeInfo['code_short_type'];
            $seaceTender->code_type = $codeInfo['code_type'];

            // ✅ MAPEO AUTOMÁTICO DE PROCESS_TYPE
            // Extraer solo el prefijo básico (antes del primer espacio)
            $basicPrefix = Str::of($seaceTender->code_short_type)->before(' ')->upper();
            $processType = \App\Models\ProcessType::where('code_short_type', $basicPrefix)->first();
            if ($processType) {
                $seaceTender->process_type = $processType->description_short_type;
            }

            // 🔧 Limpieza del identificador original (para el resto de campos)
            $cleanIdentifier = static::normalizeIdentifier($seaceTender->identifier);

            // ✅ Extraer año (formato 20XX)
            if (! preg_match('/\b(20\d{2})\b/', $cleanIdentifier, $yearMatch)) {
                throw new \Exception("Could not extract year from identifier: '{$seaceTender->identifier}'");
            }
            $seaceTender->code_year = $yearMatch[1];

            // ✅ Extraer code_sequence
            $beforeYear = explode($seaceTender->code_year, $cleanIdentifier)[0] ?? '';
            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
            $seaceTender->code_sequence = static::extractLastNumeric($segmentsBeforeYear);

            // ✅ Extraer attempt (último número en todo el string)
            preg_match_all('/\d+/', $cleanIdentifier, $allNumbers);
            $seaceTender->code_attempt = $allNumbers[0] ? (int) end($allNumbers[0]) : 1;

            // ✅ Establecer code_full normalizado (usado para búsquedas y agrupación)
            // NOTA: code_full NO es único, permite múltiples registros del mismo proceso
            $seaceTender->code_full = $cleanIdentifier;

            // ✅ Extraer base_code normalizado (proceso base sin último intento)
            // Primero extraer de identifier original, luego normalizar como code_full
            $rawBaseCode = static::extractBaseCode($seaceTender->identifier);
            $seaceTender->base_code = $rawBaseCode ? static::normalizeIdentifier($rawBaseCode) : null;

            // ✅ UNICIDAD COMPUESTA: La unicidad se valida a nivel de base de datos
            // mediante el constraint único compuesto: identifier + publish_date + resumed_from + estimated_referenced_value
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
    protected static function extractLastNumeric(array $segments): ?int
    {
        $numbers = [];
        foreach ($segments as $segment) {
            if (preg_match('/\d+/', $segment, $matches)) {
                $numbers[] = (int) $matches[0];
            }
        }

        return $numbers ? end($numbers) : null;
    }

    /**
     * Extrae el código base del identificador (sin el último intento)
     * Ejemplo: "AS-SM-13-2025-OEC/GR PUNO-2" → "AS-SM-13-2025-OEC/GR PUNO"
     *
     * @param  string  $identifier  El identificador original
     * @return string|null El código base sin el intento
     */
    public static function extractBaseCode(string $identifier): ?string
    {
        // Remover último patrón numérico (intento)
        $baseCode = preg_replace('/-\d+$/', '', trim($identifier));

        return ! empty($baseCode) ? $baseCode : null;
    }

    /**
     * Obtiene el ID del estado por defecto para importaciones Excel
     */
    public static function getDefaultTenderStatusId(): ?int
    {
        return TenderStatus::where('code', '--')->value('id');
    }

    /**
     * Scope para obtener el último intento de un proceso base
     * Considera code_attempt, publish_date y created_at para determinar el más reciente
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $baseCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatestByBaseCode($query, ?string $baseCode = null)
    {
        if ($baseCode) {
            $query->where('base_code', $baseCode);
        }

        return $query
            ->orderBy('code_attempt', 'desc')
            ->orderBy('publish_date', 'desc') // ✅ Priorizar por fecha de publicación más reciente
            ->orderBy('created_at', 'desc');
    }
}