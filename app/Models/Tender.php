<?php

namespace App\Models;

use App\Traits\HasStageMutators;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'process_type_id',
        'identifier',
        'contract_object',
        'object_description',
        'estimated_referenced_value',
        'currency_name',
        'tender_status_id',
        'seace_tender_id',
        'seace_tender_current_id', // ← Nuevo: FK a seace_tender_current.base_code
        'auto_sync_from_seace', // Control de sincronización automática
        'last_manual_update_at', // Timestamp de última actualización manual

        // Datos Adicionales
        'observation',
        'selection_comittee',
        'with_identifier',

        // Campos de auditoría de usuario
        'created_by',
        'updated_by',
    ];

    /**
     * Casts para convertir tipos automáticamente
     */
    protected $casts = [
        'estimated_referenced_value' => 'decimal:2',
        'with_identifier' => 'boolean',
        'auto_sync_from_seace' => 'boolean',
        'last_manual_update_at' => 'datetime',
    ];

    /**
     * Relaciones con las etapas del proceso
     */
    public function stages()
    {
        return $this->hasMany(TenderStage::class);
    }

    /**
     * Relación con ProcessType (usando Foreign Key)
     */
    public function processType()
    {
        return $this->belongsTo(ProcessType::class, 'process_type_id');
    }

    /**
     * @deprecated Use processType() instead. Mantenido para compatibilidad temporal.
     */
    public function processTypeRelation()
    {
        return $this->processType();
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
     * @deprecated Usar seaceTenderCurrent()->seaceTender en su lugar para obtener siempre el más reciente
     */
    public function seaceTender()
    {
        return $this->belongsTo(\App\Models\SeaceTender::class, 'seace_tender_id');
    }
    
    /**
     * Relación con SeaceTenderCurrent (lookup del más reciente)
     * Esta relación siempre apunta al SeaceTender más actualizado por base_code
     */
    public function seaceTenderCurrent(): BelongsTo
    {
        return $this->belongsTo(
            SeaceTenderCurrent::class,
            'seace_tender_current_id',
            'base_code'
        );
    }

    /**
     * Relación con reglas personalizadas de deadline
     */
    public function customDeadlineRules()
    {
        return $this->hasMany(TenderCustomDeadlineRule::class);
    }
    
    /**
     * Acceso directo al SeaceTender más reciente a través del lookup
     * Siempre obtiene el registro más actualizado del mismo base_code
     */
    public function getLatestSeaceTenderAttribute(): ?SeaceTender
    {
        if ($this->seace_tender_current_id) {
            return SeaceTenderCurrent::getLatestSeaceTender($this->seace_tender_current_id);
        }
        
        // Fallback a relación directa si no tiene lookup asignado
        return $this->seaceTender;
    }
    
    /**
     * Verificar si debe sincronizarse automáticamente desde SeaceTenderCurrent
     * 
     * @return bool  true si debe sincronizarse automáticamente
     */
    public function shouldAutoSync(): bool
    {
        // Si auto_sync está desactivado, no sincronizar
        if (!$this->auto_sync_from_seace) {
            return false;
        }
        
        // Si no tiene lookup asignado, no hay nada que sincronizar
        if (!$this->seace_tender_current_id) {
            return false;
        }
        
        // Si el usuario hizo cambios manuales recientes, no sincronizar automáticamente
        $current = $this->seaceTenderCurrent;
        if ($current && $this->last_manual_update_at && 
            $this->last_manual_update_at > $current->updated_at) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sincronizar campos desde SeaceTenderCurrent
     * 
     * @param  bool  $force  Si true, sincroniza incluso si hay cambios manuales recientes
     * @return bool  true si se sincronizó, false si no
     */
    public function syncFromSeaceTenderCurrent(bool $force = false): bool
    {
        if (!$this->seace_tender_current_id) {
            return false;
        }
        
        $latestSeaceTender = $this->latestSeaceTender;
        if (!$latestSeaceTender) {
            return false;
        }
        
        // Si no es forzado y no debe sincronizarse automáticamente, no hacerlo
        if (!$force && !$this->shouldAutoSync()) {
            return false;
        }
        
        // Campos que se sincronizan automáticamente
        $syncFields = [
            'entity_name',
            'contract_object',
            'object_description',
            'estimated_referenced_value',
            'currency_name',
            // NO sincronizamos identifier, tender_status_id, process_type_id
            // porque pueden ser modificados manualmente o tener lógica específica
        ];
        
        $updates = [];
        foreach ($syncFields as $field) {
            $updates[$field] = $latestSeaceTender->$field;
        }
        
        if (empty($updates)) {
            return false;
        }
        
        // Actualizar sin disparar eventos de auditoría (updated_by se mantiene)
        // Usamos updateQuietly para evitar loops infinitos
        $this->updateQuietly($updates);
        
        return true;
    }
    
    /**
     * Verificar si hay datos más recientes disponibles en SeaceTenderCurrent
     * 
     * @return bool  true si hay datos más recientes disponibles
     */
    public function hasNewerSeaceTenderAvailable(): bool
    {
        if (!$this->seace_tender_current_id) {
            return false;
        }
        
        $current = $this->seaceTenderCurrent;
        if (!$current) {
            return false;
        }
        
        // Si el lookup fue actualizado después de la última actualización manual del tender
        // o después de la última actualización del tender, hay datos más recientes
        $tenderUpdatedAt = $this->last_manual_update_at ?? $this->updated_at;
        
        return $current->updated_at > $tenderUpdatedAt;
    }
    
    /**
     * Obtener historial completo de SeaceTender por base_code
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSeaceTenderHistory(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->seace_tender_current_id) {
            return collect([]);
        }
        
        return \App\Models\SeaceTender::where('base_code', $this->seace_tender_current_id)
            ->orderBy('code_attempt', 'desc')
            ->orderBy('publish_date', 'desc')
            ->orderBy('publish_date_time', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Obtener el SeaceTender actual (el más reciente según SeaceTenderCurrent)
     * 
     * @return \App\Models\SeaceTender|null
     */
    public function getCurrentSeaceTender(): ?\App\Models\SeaceTender
    {
        return $this->latestSeaceTender;
    }

    /**
     * Relación con el usuario que creó el procedimiento
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Relación con el usuario que modificó por última vez
     */
    public function lastUpdater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
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
            // Asignar usuario creador automáticamente
            if (auth()->check() && !$tender->created_by) {
                $tender->created_by = auth()->id();
            }

            // Si with_identifier es false, generar identifier automático
            if (!$tender->with_identifier || empty($tender->identifier) || str_starts_with($tender->identifier, 'TEMP-GENERATED-')) {
                $tender->identifier = static::generateAutomaticIdentifier();
                $tender->with_identifier = false; // Marcar como sin nomenclatura válida
            }

            // 🔧 NUEVA LÓGICA: Extraer códigos antes de normalizar completamente
            $codeInfo = static::extractCodeInfo($tender->identifier);
            $tender->code_short_type = $codeInfo['code_short_type'];
            $tender->code_type = $codeInfo['code_type'];

            // ✅ MAPEO AUTOMÁTICO DE PROCESS_TYPE_ID
            // Extraer solo el prefijo básico (antes del primer espacio)
            $basicPrefix = Str::of($tender->code_short_type)->before(' ')->upper();
            $processType = ProcessType::where('code_short_type', $basicPrefix)->first();
            
            if ($processType) {
                $tender->process_type_id = $processType->id;
            } else {
                // Si no se encuentra el process_type, usar "Sin Clasificar"
                $sinClasificar = ProcessType::where('description_short_type', 'Sin Clasificar')->first();
                if (!$sinClasificar) {
                    // Crear "Sin Clasificar" si no existe
                    $sinClasificar = ProcessType::create([
                        'code_short_type' => 'SC',
                        'description_short_type' => 'Sin Clasificar',
                        'year' => date('Y'),
                    ]);
                }
                $tender->process_type_id = $sinClasificar->id;
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
            // Asignar usuario que modifica automáticamente
            if (auth()->check()) {
                $tender->updated_by = auth()->id();
            }

            // Si cambió el identifier, regenerar campos derivados
            if ($tender->isDirty('identifier')) {
                // Si el nuevo identifier viene de SEACE (no es temporal), regenerar campos
                if (!$tender->identifier || str_starts_with($tender->identifier, 'TEMP-GENERATED-')) {
                    // Mantener identifier temporal, no regenerar campos derivados
                    return;
                }
                
                // Regenerar todos los campos derivados
                static::regenerateCodeFields($tender);
                
                // ❌ Verificar duplicados (COMENTADO - Será removido en cambio futuro)
                // $normalized = static::normalizeIdentifier($tender->identifier);
                // $existingTender = Tender::where('code_full', $normalized)
                //     ->where('id', '!=', $tender->id)
                //     ->first();
                //     
                // if ($existingTender) {
                //     throw new \Exception("Ya existe un procedimiento con la nomenclatura: '{$tender->identifier}'");
                // }
            }
            
            // ========================================
            // TRACKEAR CAMBIOS MANUALES EN CAMPOS SINCRONIZABLES
            // ========================================
            // Si el usuario modifica manualmente campos que pueden sincronizarse desde SEACE,
            // actualizar last_manual_update_at para respetar estos cambios
            $syncFields = [
                'entity_name',
                'contract_object',
                'object_description',
                'estimated_referenced_value',
                'currency_name',
            ];
            
            $hasManualChange = false;
            foreach ($syncFields as $field) {
                if ($tender->isDirty($field)) {
                    $hasManualChange = true;
                    break;
                }
            }
            
            // Si hay cambios manuales en campos sincronizables, actualizar timestamp
            // Solo si NO está siendo sincronizado automáticamente (no viene de SeaceTenderCurrent)
            if ($hasManualChange && !$tender->isDirty('seace_tender_current_id')) {
                $tender->last_manual_update_at = now();
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

            // Actualizar process_type_id
            $basicPrefix = Str::of($tender->code_short_type)->before(' ')->upper();
            $processType = ProcessType::where('code_short_type', $basicPrefix)->first();
            
            if ($processType) {
                $tender->process_type_id = $processType->id;
            } else {
                // Si no se encuentra, usar "Sin Clasificar"
                $sinClasificar = ProcessType::where('description_short_type', 'Sin Clasificar')->first();
                if (!$sinClasificar) {
                    // Crear "Sin Clasificar" si no existe
                    $sinClasificar = ProcessType::create([
                        'code_short_type' => 'SC',
                        'description_short_type' => 'Sin Clasificar',
                        'year' => date('Y'),
                    ]);
                }
                $tender->process_type_id = $sinClasificar->id;
            }
        }
    }

    /**
     * 🎯 Obtiene la última etapa del tender
     * 
     * Retorna la etapa más avanzada que tiene datos:
     * - S4: Si tiene TenderStageS4
     * - S3: Si tiene TenderStageS3 pero no S4
     * - S2: Si tiene TenderStageS2 pero no S3 ni S4
     * - S1: Si tiene TenderStageS1 pero no S2, S3 ni S4
     * - 'No iniciado': Si no tiene ninguna etapa
     */
    public function getLastStage(): string
    {
        // Verificar en orden descendente (S4 -> S3 -> S2 -> S1)
        if ($this->s4Stage()->exists()) {
            return 'S4';
        }
        
        if ($this->s3Stage()->exists()) {
            return 'S3';
        }
        
        if ($this->s2Stage()->exists()) {
            return 'S2';
        }
        
        if ($this->s1Stage()->exists()) {
            return 'S1';
        }
        
        return 'No iniciado';
    }

    /**
     * 🎯 Obtiene el nombre descriptivo de la última etapa
     */
    public function getLastStageName(): string
    {
        return match ($this->getLastStage()) {
            'S1' => 'E1 - Actuaciones Preparatorias',
            'S2' => 'E2 - Procedimiento de Selección',
            'S3' => 'E3 - Suscripción del Contrato',
            'S4' => 'E4 - Ejecución',
            'No iniciado' => 'No iniciado',
            default => 'Desconocido',
        };
    }

    /**
     * 🎯 Scope para filtrar por última etapa
     */
    public function scopeByLastStage($query, string $stage)
    {
        return match ($stage) {
            'S4' => $query->whereHas('s4Stage'),
            'S3' => $query->whereHas('s3Stage')->whereDoesntHave('s4Stage'),
            'S2' => $query->whereHas('s2Stage')->whereDoesntHave('s3Stage')->whereDoesntHave('s4Stage'),
            'S1' => $query->whereHas('s1Stage')->whereDoesntHave('s2Stage')->whereDoesntHave('s3Stage')->whereDoesntHave('s4Stage'),
            'No iniciado' => $query->whereDoesntHave('s1Stage')->whereDoesntHave('s2Stage')->whereDoesntHave('s3Stage')->whereDoesntHave('s4Stage'),
            default => $query,
        };
    }
}
