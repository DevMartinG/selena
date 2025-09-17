<?php

namespace App\Traits;

/**
 * 🎯 TRAIT PARA MUTATORS Y ACCESSORS DE STAGES
 *
 * Este trait contiene la lógica común para manejar mutators y accessors
 * de stages, eliminando la duplicación de código en el modelo Tender.
 *
 * Características:
 * - Accessors para leer datos de stages
 * - Mutators para escribir datos de stages
 * - Creación automática de stages si no existen
 * - Manejo de campos nullable y requeridos
 */
trait HasStageMutators
{
    /**
     * 🟢 ACCESSOR para S1 Stage - Permite leer datos de la etapa S1
     */
    public function getS1StageAttribute()
    {
        $s1Stage = $this->s1Stage();

        if (! $s1Stage->exists()) {
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
     */
    public function setS1StageAttribute($value)
    {
        $this->updateStageData('S1', $value, [
            'with_certification' => true,
            'apply_selection_committee' => true,
        ]);
    }

    /**
     * 🟢 ACCESSOR para S2 Stage - Permite leer datos de la etapa S2
     */
    public function getS2StageAttribute()
    {
        $s2Stage = $this->s2Stage();

        if (! $s2Stage->exists()) {
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
        $this->updateStageData('S2', $value, [
            'published_at' => now()->toDateString(), // Campo requerido
        ]);
    }

    /**
     * 🟢 ACCESSOR para S3 Stage - Permite leer datos de la etapa S3
     */
    public function getS3StageAttribute()
    {
        $s3Stage = $this->s3Stage();

        if (! $s3Stage->exists()) {
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
        $this->updateStageData('S3', $value);
    }

    /**
     * 🟢 ACCESSOR para S4 Stage - Permite leer datos de la etapa S4
     */
    public function getS4StageAttribute()
    {
        $s4Stage = $this->s4Stage();

        if (! $s4Stage->exists()) {
            return null;
        }

        $stage = $s4Stage->first();

        return [
            'contract_details' => $stage->contract_details,
            'contract_signing' => $stage->contract_signing?->toDateString(),
            'contract_vigency_date' => $stage->contract_vigency_date?->toDateString(),
        ];
    }

    /**
     * 🔴 MUTATOR para S4 Stage - Permite escribir datos en la etapa S4
     */
    public function setS4StageAttribute($value)
    {
        $this->updateStageData('S4', $value);
    }

    /**
     * 🔧 MÉTODO COMÚN PARA ACTUALIZAR DATOS DE STAGES
     *
     * Este método centraliza la lógica de actualización de stages,
     * eliminando duplicación de código entre mutators.
     */
    private function updateStageData(string $stageType, $value, array $defaults = []): void
    {
        if (! $value || ! is_array($value)) {
            return;
        }

        // Buscar o crear la etapa principal
        $tenderStage = $this->stages()->where('stage_type', $stageType)->first();

        if (! $tenderStage) {
            $tenderStage = $this->stages()->create([
                'stage_type' => $stageType,
                'status' => 'pending',
            ]);
        }

        // Buscar o crear los datos específicos de la etapa
        $stageData = $tenderStage->{"s{$stageType[1]}Stage"};

        if (! $stageData) {
            $stageData = $tenderStage->{"s{$stageType[1]}Stage"}()->create($defaults);
        }

        // Actualizar solo los campos que tienen valores
        $updateData = [];

        foreach ($value as $field => $fieldValue) {
            if (isset($fieldValue)) {
                // Manejar campos booleanos
                if (in_array($field, ['with_certification', 'apply_selection_committee'])) {
                    $updateData[$field] = (bool) $fieldValue;
                } else {
                    $updateData[$field] = $fieldValue;
                }
            }
        }

        if (! empty($updateData)) {
            $stageData->update($updateData);
        }
    }
}
