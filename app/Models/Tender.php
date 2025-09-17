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
        'tender_status_id',

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

    /**
     * Relación con TenderStatus
     */
    public function tenderStatus()
    {
        return $this->belongsTo(TenderStatus::class, 'tender_status_id');
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

    // ========================================================================
    // 🎯 MUTATORS Y ACCESSORS PARA STAGES
    // ========================================================================
    // Estos métodos permiten que Filament trate los campos de stages como
    // si fueran atributos directos del modelo Tender, facilitando el CRUD completo

    /**
     * 🟢 ACCESSOR para S1 Stage - Permite leer datos de la etapa S1
     * Convierte la relación s1Stage en un array accesible para Filament
     */
    public function getS1StageAttribute()
    {
        $s1Stage = $this->s1Stage();
        
        if (!$s1Stage->exists()) {
            return null;
        }

        $stage = $s1Stage->first();
        
        return [
            'request_presentation_doc' => $stage->request_presentation_doc,
            'request_presentation_date' => $stage->request_presentation_date?->toDateString(),
            'market_indagation_doc' => $stage->market_indagation_doc,
            'market_indagation_date' => $stage->market_indagation_date?->toDateString(),
            'with_certification' => $stage->with_certification,
            'certification_date' => $stage->certification_date?->toDateString(),
            'no_certification_reason' => $stage->no_certification_reason,
            'approval_expedient_date' => $stage->approval_expedient_date?->toDateString(),
            'apply_selection_committee' => $stage->apply_selection_committee,
            'selection_committee_date' => $stage->selection_committee_date?->toDateString(),
            'administrative_bases_date' => $stage->administrative_bases_date?->toDateString(),
            'approval_expedient_format_2' => $stage->approval_expedient_format_2?->toDateString(),
        ];
    }

    /**
     * 🔴 MUTATOR para S1 Stage - Permite escribir datos en la etapa S1
     * Convierte los datos del formulario en actualizaciones de la tabla S1
     */
    public function setS1StageAttribute($value)
    {
        if (!$value || !is_array($value)) {
            return;
        }

        // Buscar o crear la etapa S1
        $tenderStage = $this->stages()->where('stage_type', 'S1')->first();
        
        if (!$tenderStage) {
            // Crear la etapa S1 si no existe
            $tenderStage = $this->stages()->create([
                'stage_type' => 'S1',
                'status' => 'pending',
            ]);
        }

        // Buscar o crear los datos específicos de S1
        $s1Data = $tenderStage->s1Stage;
        
        if (!$s1Data) {
            $s1Data = $tenderStage->s1Stage()->create([
                'with_certification' => true,
                'apply_selection_committee' => true,
            ]);
        }

        // Actualizar solo los campos que tienen valores
        $updateData = [];
        
        if (isset($value['request_presentation_doc'])) {
            $updateData['request_presentation_doc'] = $value['request_presentation_doc'];
        }
        if (isset($value['request_presentation_date'])) {
            $updateData['request_presentation_date'] = $value['request_presentation_date'];
        }
        if (isset($value['market_indagation_doc'])) {
            $updateData['market_indagation_doc'] = $value['market_indagation_doc'];
        }
        if (isset($value['market_indagation_date'])) {
            $updateData['market_indagation_date'] = $value['market_indagation_date'];
        }
        if (isset($value['with_certification'])) {
            $updateData['with_certification'] = (bool) $value['with_certification'];
        }
        if (isset($value['certification_date'])) {
            $updateData['certification_date'] = $value['certification_date'];
        }
        if (isset($value['no_certification_reason'])) {
            $updateData['no_certification_reason'] = $value['no_certification_reason'];
        }
        if (isset($value['approval_expedient_date'])) {
            $updateData['approval_expedient_date'] = $value['approval_expedient_date'];
        }
        if (isset($value['apply_selection_committee'])) {
            $updateData['apply_selection_committee'] = (bool) $value['apply_selection_committee'];
        }
        if (isset($value['selection_committee_date'])) {
            $updateData['selection_committee_date'] = $value['selection_committee_date'];
        }
        if (isset($value['administrative_bases_date'])) {
            $updateData['administrative_bases_date'] = $value['administrative_bases_date'];
        }
        if (isset($value['approval_expedient_format_2'])) {
            $updateData['approval_expedient_format_2'] = $value['approval_expedient_format_2'];
        }

        if (!empty($updateData)) {
            $s1Data->update($updateData);
        }
    }

    /**
     * 🟢 ACCESSOR para S2 Stage - Permite leer datos de la etapa S2
     */
    public function getS2StageAttribute()
    {
        $s2Stage = $this->s2Stage();
        
        if (!$s2Stage->exists()) {
            return null;
        }

        $stage = $s2Stage->first();
        
        return [
            'published_at' => $stage->published_at?->toDateString(),
            'participants_registration' => $stage->participants_registration?->toDateString(),
            'restarted_from' => $stage->restarted_from,
            'cui_code' => $stage->cui_code,
            'absolution_obs' => $stage->absolution_obs?->toDateString(),
            'base_integration' => $stage->base_integration?->toDateString(),
            'offer_presentation' => $stage->offer_presentation?->toDateString(),
            'offer_evaluation' => $stage->offer_evaluation?->toDateString(),
            'award_granted_at' => $stage->award_granted_at?->toDateString(),
            'award_consent' => $stage->award_consent?->toDateString(),
            'appeal_date' => $stage->appeal_date?->toDateString(),
            'awarded_tax_id' => $stage->awarded_tax_id,
            'awarded_legal_name' => $stage->awarded_legal_name,
        ];
    }

    /**
     * 🔴 MUTATOR para S2 Stage - Permite escribir datos en la etapa S2
     */
    public function setS2StageAttribute($value)
    {
        if (!$value || !is_array($value)) {
            return;
        }

        // Buscar o crear la etapa S2
        $tenderStage = $this->stages()->where('stage_type', 'S2')->first();
        
        if (!$tenderStage) {
            $tenderStage = $this->stages()->create([
                'stage_type' => 'S2',
                'status' => 'pending',
            ]);
        }

        // Buscar o crear los datos específicos de S2
        $s2Data = $tenderStage->s2Stage;
        
        if (!$s2Data) {
            $s2Data = $tenderStage->s2Stage()->create([
                'published_at' => now()->toDateString(), // Valor por defecto requerido
            ]);
        }

        // Actualizar solo los campos que tienen valores
        $updateData = [];
        
        if (isset($value['published_at'])) {
            $updateData['published_at'] = $value['published_at'];
        }
        if (isset($value['participants_registration'])) {
            $updateData['participants_registration'] = $value['participants_registration'];
        }
        if (isset($value['restarted_from'])) {
            $updateData['restarted_from'] = $value['restarted_from'];
        }
        if (isset($value['cui_code'])) {
            $updateData['cui_code'] = $value['cui_code'];
        }
        if (isset($value['absolution_obs'])) {
            $updateData['absolution_obs'] = $value['absolution_obs'];
        }
        if (isset($value['base_integration'])) {
            $updateData['base_integration'] = $value['base_integration'];
        }
        if (isset($value['offer_presentation'])) {
            $updateData['offer_presentation'] = $value['offer_presentation'];
        }
        if (isset($value['offer_evaluation'])) {
            $updateData['offer_evaluation'] = $value['offer_evaluation'];
        }
        if (isset($value['award_granted_at'])) {
            $updateData['award_granted_at'] = $value['award_granted_at'];
        }
        if (isset($value['award_consent'])) {
            $updateData['award_consent'] = $value['award_consent'];
        }
        if (isset($value['appeal_date'])) {
            $updateData['appeal_date'] = $value['appeal_date'];
        }
        if (isset($value['awarded_tax_id'])) {
            $updateData['awarded_tax_id'] = $value['awarded_tax_id'];
        }
        if (isset($value['awarded_legal_name'])) {
            $updateData['awarded_legal_name'] = $value['awarded_legal_name'];
        }

        if (!empty($updateData)) {
            $s2Data->update($updateData);
        }
    }

    /**
     * 🟢 ACCESSOR para S3 Stage - Permite leer datos de la etapa S3
     */
    public function getS3StageAttribute()
    {
        $s3Stage = $this->s3Stage();
        
        if (!$s3Stage->exists()) {
            return null;
        }

        $stage = $s3Stage->first();
        
        return [
            'doc_sign_presentation_date' => $stage->doc_sign_presentation_date?->toDateString(),
            'contract_signing' => $stage->contract_signing?->toDateString(),
            'awarded_amount' => $stage->awarded_amount,
            'adjusted_amount' => $stage->adjusted_amount,
        ];
    }

    /**
     * 🔴 MUTATOR para S3 Stage - Permite escribir datos en la etapa S3
     */
    public function setS3StageAttribute($value)
    {
        if (!$value || !is_array($value)) {
            return;
        }

        // Buscar o crear la etapa S3
        $tenderStage = $this->stages()->where('stage_type', 'S3')->first();
        
        if (!$tenderStage) {
            $tenderStage = $this->stages()->create([
                'stage_type' => 'S3',
                'status' => 'pending',
            ]);
        }

        // Buscar o crear los datos específicos de S3
        $s3Data = $tenderStage->s3Stage;
        
        if (!$s3Data) {
            $s3Data = $tenderStage->s3Stage()->create([]);
        }

        // Actualizar solo los campos que tienen valores
        $updateData = [];
        
        if (isset($value['doc_sign_presentation_date'])) {
            $updateData['doc_sign_presentation_date'] = $value['doc_sign_presentation_date'];
        }
        if (isset($value['contract_signing'])) {
            $updateData['contract_signing'] = $value['contract_signing'];
        }
        if (isset($value['awarded_amount'])) {
            $updateData['awarded_amount'] = $value['awarded_amount'];
        }
        if (isset($value['adjusted_amount'])) {
            $updateData['adjusted_amount'] = $value['adjusted_amount'];
        }

        if (!empty($updateData)) {
            $s3Data->update($updateData);
        }
    }

    /**
     * 🟢 ACCESSOR para S4 Stage - Permite leer datos de la etapa S4
     */
    public function getS4StageAttribute()
    {
        $s4Stage = $this->s4Stage();
        
        if (!$s4Stage->exists()) {
            return null;
        }

        $stage = $s4Stage->first();
        
        return [
            'contract_details' => $stage->contract_details,
            'contract_signing' => $stage->contract_signing,
            'contract_vigency_date' => $stage->contract_vigency_date,
        ];
    }

    /**
     * 🔴 MUTATOR para S4 Stage - Permite escribir datos en la etapa S4
     */
    public function setS4StageAttribute($value)
    {
        if (!$value || !is_array($value)) {
            return;
        }

        // Buscar o crear la etapa S4
        $tenderStage = $this->stages()->where('stage_type', 'S4')->first();
        
        if (!$tenderStage) {
            $tenderStage = $this->stages()->create([
                'stage_type' => 'S4',
                'status' => 'pending',
            ]);
        }

        // Buscar o crear los datos específicos de S4
        $s4Data = $tenderStage->s4Stage;
        
        if (!$s4Data) {
            $s4Data = $tenderStage->s4Stage()->create([]);
        }

        // Actualizar solo los campos que tienen valores
        $updateData = [];
        
        if (isset($value['contract_details'])) {
            $updateData['contract_details'] = $value['contract_details'];
        }
        if (isset($value['contract_signing'])) {
            $updateData['contract_signing'] = $value['contract_signing'];
        }
        if (isset($value['contract_vigency_date'])) {
            $updateData['contract_vigency_date'] = $value['contract_vigency_date'];
        }

        if (!empty($updateData)) {
            $s4Data->update($updateData);
        }
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
