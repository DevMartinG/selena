<?php

namespace App\Traits;

use App\Models\TenderStage;
use Filament\Notifications\Notification;

trait TenderWorkflowValidation
{
    /**
     * Valida si se puede avanzar a una etapa específica
     */
    public function canAdvanceToStage(string $targetStage): bool
    {
        $tender = $this->record ?? $this;
        
        // Definir el orden de las etapas
        $stageOrder = ['S1', 'S2', 'S3', 'S4'];
        $targetIndex = array_search($targetStage, $stageOrder);
        
        if ($targetIndex === false) {
            return false;
        }
        
        // Verificar que todas las etapas anteriores estén completadas
        for ($i = 0; $i < $targetIndex; $i++) {
            $previousStage = $tender->stages()->where('stage_type', $stageOrder[$i])->first();
            
            if (!$previousStage || $previousStage->status !== 'completed') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida y actualiza el estado de una etapa
     */
    public function validateAndUpdateStageStatus(string $stageType, string $newStatus): bool
    {
        $tender = $this->record ?? $this;
        $stage = $tender->stages()->where('stage_type', $stageType)->first();
        
        if (!$stage) {
            $this->showStageNotFoundError($stageType);
            return false;
        }
        
        // Validar transiciones de estado
        if (!$this->isValidStatusTransition($stage->status, $newStatus)) {
            $this->showInvalidTransitionError($stage->status, $newStatus);
            return false;
        }
        
        // Validar workflow si se está completando una etapa
        if ($newStatus === 'completed' && !$this->canCompleteStage($stageType)) {
            $this->showCannotCompleteStageError($stageType);
            return false;
        }
        
        // Actualizar estado
        $stage->update([
            'status' => $newStatus,
            'started_at' => $newStatus === 'in_progress' && !$stage->started_at ? now() : $stage->started_at,
            'completed_at' => $newStatus === 'completed' ? now() : null,
        ]);
        
        $this->showStageStatusUpdatedNotification($stageType, $newStatus);
        return true;
    }
    
    /**
     * Verifica si se puede completar una etapa específica
     */
    private function canCompleteStage(string $stageType): bool
    {
        $tender = $this->record ?? $this;
        $stage = $tender->stages()->where('stage_type', $stageType)->first();
        
        if (!$stage) {
            return false;
        }
        
        // Validaciones específicas por etapa
        return match ($stageType) {
            'S1' => $this->canCompleteS1($stage),
            'S2' => $this->canCompleteS2($stage),
            'S3' => $this->canCompleteS3($stage),
            'S4' => $this->canCompleteS4($stage),
            default => true,
        };
    }
    
    /**
     * Valida si se puede completar la etapa S1
     */
    private function canCompleteS1(TenderStage $stage): bool
    {
        $s1Data = $stage->s1Stage;
        
        if (!$s1Data) {
            return false;
        }
        
        // Validaciones específicas para S1
        $requiredFields = [
            'request_presentation_date',
            'market_indagation_date',
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($s1Data->$field)) {
                $this->showMissingRequiredFieldError('S1', $field);
                return false;
            }
        }
        
        // Si no tiene certificación, debe tener motivo
        if (!$s1Data->with_certification && empty($s1Data->no_certification_reason)) {
            $this->showMissingRequiredFieldError('S1', 'no_certification_reason');
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida si se puede completar la etapa S2
     */
    private function canCompleteS2(TenderStage $stage): bool
    {
        $s2Data = $stage->s2Stage;
        
        if (!$s2Data) {
            return false;
        }
        
        // Validaciones específicas para S2
        $requiredFields = [
            'published_at',
            'offer_presentation',
            'offer_evaluation',
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($s2Data->$field)) {
                $this->showMissingRequiredFieldError('S2', $field);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida si se puede completar la etapa S3
     */
    private function canCompleteS3(TenderStage $stage): bool
    {
        $s3Data = $stage->s3Stage;
        
        if (!$s3Data) {
            return false;
        }
        
        // Validaciones específicas para S3
        $requiredFields = [
            'contract_signing',
            'awarded_amount',
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($s3Data->$field)) {
                $this->showMissingRequiredFieldError('S3', $field);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida si se puede completar la etapa S4
     */
    private function canCompleteS4(TenderStage $stage): bool
    {
        $s4Data = $stage->s4Stage;
        
        if (!$s4Data) {
            return false;
        }
        
        // Validaciones específicas para S4
        $requiredFields = [
            'contract_details',
            'contract_vigency_date',
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($s4Data->$field)) {
                $this->showMissingRequiredFieldError('S4', $field);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verifica si una transición de estado es válida
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => ['in_progress'], // Permitir reabrir para correcciones
            'cancelled' => ['pending'], // Permitir reactivar
        ];
        
        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
    
    /**
     * Obtiene el progreso general del tender
     */
    public function getTenderProgress(): array
    {
        $tender = $this->record ?? $this;
        $stages = $tender->stages()->get();
        
        $progress = [
            'total_stages' => 4,
            'completed_stages' => 0,
            'in_progress_stages' => 0,
            'pending_stages' => 0,
            'cancelled_stages' => 0,
            'percentage' => 0,
        ];
        
        foreach ($stages as $stage) {
            match ($stage->status) {
                'completed' => $progress['completed_stages']++,
                'in_progress' => $progress['in_progress_stages']++,
                'pending' => $progress['pending_stages']++,
                'cancelled' => $progress['cancelled_stages']++,
            };
        }
        
        $progress['percentage'] = round(($progress['completed_stages'] / $progress['total_stages']) * 100);
        
        return $progress;
    }
    
    // Métodos de notificaciones
    private function showStageNotFoundError(string $stageType): void
    {
        Notification::make()
            ->title('Etapa no encontrada')
            ->body("No se encontró la etapa {$stageType} para este procedimiento.")
            ->danger()
            ->send();
    }
    
    private function showInvalidTransitionError(string $currentStatus, string $newStatus): void
    {
        Notification::make()
            ->title('Transición de estado inválida')
            ->body("No se puede cambiar de '{$currentStatus}' a '{$newStatus}'.")
            ->warning()
            ->send();
    }
    
    private function showCannotCompleteStageError(string $stageType): void
    {
        Notification::make()
            ->title('No se puede completar la etapa')
            ->body("La etapa {$stageType} no cumple con los requisitos para ser completada.")
            ->warning()
            ->send();
    }
    
    private function showMissingRequiredFieldError(string $stageType, string $field): void
    {
        Notification::make()
            ->title('Campo requerido faltante')
            ->body("El campo '{$field}' es requerido para completar la etapa {$stageType}.")
            ->warning()
            ->send();
    }
    
    private function showStageStatusUpdatedNotification(string $stageType, string $newStatus): void
    {
        Notification::make()
            ->title('Estado actualizado')
            ->body("La etapa {$stageType} ha sido actualizada a '{$newStatus}'.")
            ->success()
            ->send();
    }
}
