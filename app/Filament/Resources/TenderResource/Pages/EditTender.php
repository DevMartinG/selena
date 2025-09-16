<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Models\TenderStage;
use App\Models\TenderStageS1;
use App\Models\TenderStageS2;
use App\Models\TenderStageS3;
use App\Models\TenderStageS4;
use App\Traits\TenderStageInitializer;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditTender extends EditRecord
{
    use TenderStageInitializer;

    protected static string $resource = TenderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_s1')
                ->label('Crear Etapa 1')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s1Stage)
                ->action(function () {
                    $this->initializeStage('S1');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s2')
                ->label('Crear Etapa 2')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s2Stage && $this->record->s1Stage)
                ->action(function () {
                    $this->initializeStage('S2');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s3')
                ->label('Crear Etapa 3')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s3Stage && $this->record->s2Stage)
                ->action(function () {
                    $this->initializeStage('S3');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s4')
                ->label('Crear Etapa 4')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s4Stage && $this->record->s3Stage)
                ->action(function () {
                    $this->initializeStage('S4');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('duplicate_tender')
                ->label('Duplicar Procedimiento')
                ->icon('heroicon-m-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Duplicar Procedimiento')
                ->modalDescription('¿Está seguro de que desea duplicar este procedimiento? Se creará una copia con todas sus etapas.')
                ->action(function () {
                    $this->duplicateTender();
                }),

            Action::make('export_data')
                ->label('Exportar Datos')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $this->exportTenderData();
                }),

            Actions\DeleteAction::make()
                ->label('Eliminar Procedimiento')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Procedimiento')
                ->modalDescription('¿Está seguro de que desea eliminar este procedimiento? Esta acción eliminará también todas las etapas asociadas y no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar Cambios')
                ->icon('heroicon-m-check')
                ->color('success'),
            $this->getCancelFormAction()
                ->label('Cancelar')
                ->color('gray'),
        ];
    }

    private function getExistingStages(): array
    {
        $existingStages = $this->record->stages()->pluck('stage_type')->toArray();

        return array_intersect(['S1', 'S2', 'S3', 'S4'], $existingStages);
    }

    private function duplicateTender(): void
    {
        $originalTender = $this->record;

        // Crear nuevo tender con datos básicos
        $newTender = Tender::create([
            'entity_name' => $originalTender->entity_name,
            'process_type' => $originalTender->process_type,
            'identifier' => $originalTender->identifier.'-COPIA',
            'contract_object' => $originalTender->contract_object,
            'object_description' => $originalTender->object_description,
            'estimated_referenced_value' => $originalTender->estimated_referenced_value,
            'currency_name' => $originalTender->currency_name,
            'current_status' => '1-CONVOCADO', // Estado inicial para la copia
            'observation' => $originalTender->observation,
            'selection_comittee' => $originalTender->selection_comittee,
        ]);

        // Duplicar etapas
        foreach ($originalTender->stages as $stage) {
            $newStage = TenderStage::create([
                'tender_id' => $newTender->id,
                'stage_type' => $stage->stage_type,
                'status' => 'pending', // Estado inicial para las copias
                'started_at' => null,
                'completed_at' => null,
            ]);

            // Duplicar datos específicos de cada etapa
            match ($stage->stage_type) {
                'S1' => $this->duplicateStageS1($stage, $newStage),
                'S2' => $this->duplicateStageS2($stage, $newStage),
                'S3' => $this->duplicateStageS3($stage, $newStage),
                'S4' => $this->duplicateStageS4($stage, $newStage),
            };
        }

        Notification::make()
            ->title('Procedimiento duplicado exitosamente')
            ->body("Se ha creado una copia del procedimiento con ID: {$newTender->id}")
            ->success()
            ->send();

        // Redirigir al nuevo tender
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $newTender]));
    }

    private function duplicateStageS1($originalStage, $newStage): void
    {
        $s1Data = $originalStage->s1Stage;
        if ($s1Data) {
            TenderStageS1::create([
                'tender_stage_id' => $newStage->id,
                'request_presentation_doc' => $s1Data->request_presentation_doc,
                'request_presentation_date' => null, // Resetear fechas
                'market_indagation_doc' => $s1Data->market_indagation_doc,
                'market_indagation_date' => null,
                'with_certification' => $s1Data->with_certification,
                'certification_date' => null,
                'no_certification_reason' => $s1Data->no_certification_reason,
                'approval_expedient_date' => null,
                'selection_committee_date' => null,
                'administrative_bases_date' => null,
                'approval_expedient_format_2' => null,
            ]);
        }
    }

    private function duplicateStageS2($originalStage, $newStage): void
    {
        $s2Data = $originalStage->s2Stage;
        if ($s2Data) {
            TenderStageS2::create([
                'tender_stage_id' => $newStage->id,
                'published_at' => null, // Resetear fechas
                'participants_registration' => null,
                'restarted_from' => $s2Data->restarted_from,
                'cui_code' => $s2Data->cui_code,
                'absolution_obs' => null,
                'base_integration' => null,
                'offer_presentation' => null,
                'offer_evaluation' => null,
                'award_granted_at' => null,
                'award_consent' => null,
                'appeal_date' => null,
                'awarded_tax_id' => $s2Data->awarded_tax_id,
                'awarded_legal_name' => $s2Data->awarded_legal_name,
            ]);
        }
    }

    private function duplicateStageS3($originalStage, $newStage): void
    {
        $s3Data = $originalStage->s3Stage;
        if ($s3Data) {
            TenderStageS3::create([
                'tender_stage_id' => $newStage->id,
                'doc_sign_presentation_date' => null,
                'contract_signing' => null,
                'awarded_amount' => $s3Data->awarded_amount,
                'adjusted_amount' => $s3Data->adjusted_amount,
            ]);
        }
    }

    private function duplicateStageS4($originalStage, $newStage): void
    {
        $s4Data = $originalStage->s4Stage;
        if ($s4Data) {
            TenderStageS4::create([
                'tender_stage_id' => $newStage->id,
                'contract_details' => $s4Data->contract_details,
                'contract_signing' => $s4Data->contract_signing,
                'contract_vigency_date' => $s4Data->contract_vigency_date,
            ]);
        }
    }

    private function exportTenderData(): void
    {
        $tender = $this->record;

        // Aquí podrías implementar la lógica de exportación
        // Por ejemplo, generar un PDF o Excel con todos los datos

        Notification::make()
            ->title('Datos exportados')
            ->body('Los datos del procedimiento se han exportado exitosamente.')
            ->success()
            ->send();
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        // return MaxWidth::ScreenTwoExtraLarge;
        return MaxWidth::ScreenExtraLarge;
    }
}
