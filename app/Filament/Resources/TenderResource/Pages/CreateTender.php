<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Models\Tender;
use App\Models\TenderStage;
use App\Models\TenderStageS1;
use App\Models\TenderStageS2;
use App\Models\TenderStageS3;
use App\Models\TenderStageS4;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateTender extends CreateRecord
{
    protected static string $resource = TenderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Reutilizar lógica del modelo para estandarizar el identifier
        $normalized = Tender::normalizeIdentifier($data['identifier']);

        // Verificamos duplicado
        if (Tender::where('code_full', $normalized)->exists()) {
            Notification::make()
                ->title('Nomenclatura duplicada')
                ->body("Ya existe un procedimiento con una nomenclatura equivalente a <strong>{$data['identifier']}</strong>.")
                ->icon('heroicon-s-exclamation-triangle')
                ->warning()
                ->duration(5000)
                ->send();

            $this->halt(); // Detiene el guardado
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Crear etapas iniciales automáticamente
        $this->createInitialStages();
        
        Notification::make()
            ->title('Procedimiento creado exitosamente')
            ->body('Se han inicializado las etapas del proceso. Puede comenzar a completar los datos de cada etapa.')
            ->success()
            ->send();
    }

    private function createInitialStages(): void
    {
        $tender = $this->record;

        // Crear etapa S1 (Actuaciones Preparatorias)
        $s1Stage = TenderStage::create([
            'tender_id' => $tender->id,
            'stage_type' => 'S1',
            'status' => 'pending',
        ]);

        TenderStageS1::create([
            'tender_stage_id' => $s1Stage->id,
        ]);

        // Crear etapa S2 (Procedimiento de Selección)
        $s2Stage = TenderStage::create([
            'tender_id' => $tender->id,
            'stage_type' => 'S2',
            'status' => 'pending',
        ]);

        TenderStageS2::create([
            'tender_stage_id' => $s2Stage->id,
        ]);

        // Crear etapa S3 (Suscripción del Contrato)
        $s3Stage = TenderStage::create([
            'tender_id' => $tender->id,
            'stage_type' => 'S3',
            'status' => 'pending',
        ]);

        TenderStageS3::create([
            'tender_stage_id' => $s3Stage->id,
        ]);

        // Crear etapa S4 (Tiempo de Ejecución)
        $s4Stage = TenderStage::create([
            'tender_id' => $tender->id,
            'stage_type' => 'S4',
            'status' => 'pending',
        ]);

        TenderStageS4::create([
            'tender_stage_id' => $s4Stage->id,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_with_stages')
                ->label('Crear con Etapas Personalizadas')
                ->icon('heroicon-m-cog-6-tooth')
                ->color('info')
                ->form([
                    CheckboxList::make('stages_to_create')
                        ->label('Etapas a crear automáticamente')
                        ->options([
                            'S1' => 'S1 - Actuaciones Preparatorias',
                            'S2' => 'S2 - Procedimiento de Selección',
                            'S3' => 'S3 - Suscripción del Contrato',
                            'S4' => 'S4 - Tiempo de Ejecución',
                        ])
                        ->default(['S1', 'S2', 'S3', 'S4'])
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Esta acción se ejecutará después de crear el tender
                    $this->customStagesToCreate = $data['stages_to_create'];
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Crear Procedimiento')
                ->icon('heroicon-m-plus')
                ->color('success'),
            $this->getCancelFormAction()
                ->label('Cancelar')
                ->color('gray'),
        ];
    }
}
