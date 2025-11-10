<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\TenderDeadlineRule;
use App\Services\TenderFieldExtractor;
use Filament\Forms;

/**
 * 🎯 HELPER: CUSTOM DEADLINE RULE MANAGER
 *
 * Este helper proporciona métodos para gestionar reglas personalizadas de deadline
 * por Tender. Permite crear formularios y acciones para definir reglas personalizadas
 * con evidencia que sobrescriben las reglas globales.
 */
class CustomDeadlineRuleManager
{
    /**
     * 🎯 Crea el formulario para gestionar una regla personalizada
     *
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo (ej: s1Stage.approval_expedient_date)
     * @param  \App\Models\TenderCustomDeadlineRule|null  $existingRule  Regla existente (si se está editando)
     * @return array
     */
    public static function createRuleForm(string $stageType, string $fieldName, $existingRule = null): array
    {
        // Obtener el label del campo para mostrar información
        $fieldOptions = TenderFieldExtractor::getFieldOptionsByStage($stageType);
        $fieldLabel = $fieldOptions[$fieldName] ?? $fieldName;

        return [
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\DatePicker::make('custom_date')
                        ->label('Fecha Personalizada')
                        ->required()
                        ->default($existingRule?->custom_date)
                        ->helperText("Fecha de referencia para la validación personalizada del campo \"{$fieldLabel}\". Esta fecha actúa como \"fecha programada\" para este campo.")
                        ->columnSpan(1),
                    
                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->default($existingRule?->description)
                        ->placeholder('Descripción opcional de la regla personalizada...')
                        ->rows(3)
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make('evidence_image')
                        ->label('Evidencia (Imagen Captura)')
                        ->image()
                        ->acceptedFileTypes(['image/*'])
                        ->maxSize(5120) // 5MB
                        ->directory('tenders/custom_deadline_evidence')
                        ->visibility('private')
                        ->required(! $existingRule) // Solo requerido si es nueva regla
                        ->default($existingRule?->evidence_image)
                        ->helperText('Captura del PDF que avala la fecha personalizada (obligatoria)')
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make('evidence_pdf')
                        ->label('Evidencia (PDF Completo)')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->directory('tenders/custom_deadline_evidence')
                        ->visibility('private')
                        ->default($existingRule?->evidence_pdf)
                        ->helperText('PDF completo que avala la fecha (opcional)')
                        ->columnSpan(1),

                ])
                ->columns(2),
        ];
    }

    /**
     * 🎯 Guarda o actualiza una regla personalizada
     *
     * @param  \App\Models\Tender  $tender  Tender al que pertenece la regla
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  array  $data  Datos del formulario
     * @return \App\Models\TenderCustomDeadlineRule
     */
    public static function saveCustomRule($tender, string $stageType, string $fieldName, array $data)
    {
        // Obtener automáticamente from_stage y from_field desde la primera regla global que aplica
        // Si no hay regla global, usar valores por defecto basados en el campo actual
        $globalRule = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->first();

        $fromStage = $globalRule?->from_stage ?? $stageType;
        $fromField = $globalRule?->from_field ?? $fieldName;

        return \App\Models\TenderCustomDeadlineRule::updateOrCreate(
            [
                'tender_id' => $tender->id,
                'stage_type' => $stageType,
                'field_name' => $fieldName,
            ],
            [
                'from_stage' => $fromStage,
                'from_field' => $fromField,
                'custom_date' => $data['custom_date'],
                'evidence_image' => $data['evidence_image'] ?? null,
                'evidence_pdf' => $data['evidence_pdf'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * 🎯 Elimina una regla personalizada
     *
     * @param  \App\Models\Tender  $tender  Tender al que pertenece la regla
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return bool
     */
    public static function deleteCustomRule($tender, string $stageType, string $fieldName): bool
    {
        return \App\Models\TenderCustomDeadlineRule::forTender($tender->id)
            ->forField($stageType, $fieldName)
            ->delete() > 0;
    }

    /**
     * 🎯 Verifica si existe una regla personalizada
     *
     * @param  \App\Models\Tender  $tender  Tender al que pertenece la regla
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return bool
     */
    public static function hasCustomRule($tender, string $stageType, string $fieldName): bool
    {
        if (! $tender || ! $tender->id) {
            return false;
        }

        return \App\Models\TenderCustomDeadlineRule::hasCustomRule($tender->id, $stageType, $fieldName);
    }

    /**
     * 🎯 Obtiene una regla personalizada existente
     *
     * @param  \App\Models\Tender  $tender  Tender al que pertenece la regla
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return \App\Models\TenderCustomDeadlineRule|null
     */
    public static function getCustomRule($tender, string $stageType, string $fieldName)
    {
        if (! $tender || ! $tender->id) {
            return null;
        }

        return \App\Models\TenderCustomDeadlineRule::getCustomRule($tender->id, $stageType, $fieldName);
    }

    /**
     * 🎯 Genera las acciones hintActions para un campo de fecha
     *
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return array
     */
    public static function createHintActions(string $stageType, string $fieldName): array
    {
        $actions = [];
        
        // Acción de gestionar (siempre visible si hay record)
        $actions[] = Forms\Components\Actions\Action::make('manage_custom_rule')
            ->label(false)
            ->icon('heroicon-m-cog-6-tooth')
            ->color('info')
            ->size('sm')
            ->tooltip('Click para gestionar Regla Personalizada')
            ->extraAttributes(['style' => 'margin-left: auto;'])
            ->modalHeading('Gestionar Regla Personalizada')
            ->modalDescription('Define una fecha personalizada con evidencia para sobrescribir la regla global')
            ->modalWidth('2xl')
            ->form(function ($record) use ($stageType, $fieldName) {
                $existingRule = self::getCustomRule($record, $stageType, $fieldName);
                return self::createRuleForm($stageType, $fieldName, $existingRule);
            })
            ->action(function (array $data, $record) use ($stageType, $fieldName) {
                self::saveCustomRule($record, $stageType, $fieldName, $data);
                
                \Filament\Notifications\Notification::make()
                    ->title('Regla personalizada guardada')
                    ->body('La regla personalizada se ha guardado correctamente')
                    ->success()
                    ->send();
            })
            ->visible(fn ($record) => $record && $record->id);

        // Acción de eliminar (solo visible si existe regla personalizada)
        $actions[] = Forms\Components\Actions\Action::make('remove_custom_rule')
            ->label(false)
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->size('sm')
            ->tooltip('Click para eliminar Regla Personalizada')
            ->requiresConfirmation()
            ->modalHeading('Eliminar Regla Personalizada')
            ->modalDescription('¿Estás seguro de que deseas eliminar esta regla personalizada? Se volverá a usar la regla global.')
            ->action(function ($record) use ($stageType, $fieldName) {
                self::deleteCustomRule($record, $stageType, $fieldName);
                
                \Filament\Notifications\Notification::make()
                    ->title('Regla personalizada eliminada')
                    ->body('La regla personalizada se ha eliminado correctamente')
                    ->success()
                    ->send();
            })
            ->visible(function ($record) use ($stageType, $fieldName) {
                return $record && $record->id && self::hasCustomRule($record, $stageType, $fieldName);
            });

        return $actions;
    }
}

