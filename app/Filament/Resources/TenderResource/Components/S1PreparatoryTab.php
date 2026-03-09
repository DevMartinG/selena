<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Filament\Resources\TenderResource\Components\Shared\DateCalculations;
use App\Filament\Resources\TenderResource\Components\Shared\StageHelpers;
use App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper;
use App\Filament\Resources\TenderResource\Components\Shared\CustomDeadlineRuleManager;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

use App\Models\Meta;

use Illuminate\Support\Facades\Log;


/**
 * 🎯 COMPONENTE: TAB S1 PREPARATORIAS
 *
 * Este componente maneja la etapa S1 (Actuaciones Preparatorias) del Tender
 * en el tab "1.Act. Preparatorias" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Presentación de Requerimiento de Bien
 * - Indagación de Mercado
 * - Certificación (con lógica condicional)
 * - Aprobación del Expediente de Contratación
 * - Designación del Comité de Selección (con lógica condicional)
 * - Elaboración de Bases Administrativas
 * - Aprobación de Bases Administrativas Formato 2
 * - Cálculo automático de días calendario y hábiles
 * - Validación de estados de etapa (creada/pendiente)
 *
 * CARACTERÍSTICAS TÉCNICAS:
 * - Usa componentes compartidos de DateCalculations y StageHelpers
 * - Maneja lógica condicional para certificación y comité
 * - Campos reactivos con live() para cálculos automáticos
 * - Validación de fechas con iconos de bandera
 * - Distribución en Grid de 8 columnas
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab S1 Preparatory
 * - Mantiene toda la funcionalidad original
 */
class S1PreparatoryTab
{
    /**
     * 🎯 Crea el schema completo del tab S1 Preparatory
     *
     * @return array Array de componentes para el schema del tab
     */
    public static function getSchema(): array
    {
        return [
            // ========================================================================
            // 📋 CAMPOS DE LA ETAPA S1 - ACTUACIONES PREPARATORIAS
            // ========================================================================

            // ========================================================================
            // 📊 GRID PRINCIPAL CON TODAS LAS SECCIONES
            // ========================================================================
            Grid::make(10)
                ->schema([
                    // ========================================================================
                    // 📋 SECCIÓN 1: PRESENTACIÓN DE REQUERIMIENTO DE BIEN
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Presentación de Requerimiento de Bien', ''))
                        ->compact()
                        ->schema([
                            // Campo de documento con hintAction integrado
                            TextInput::make('s1Stage.request_presentation_doc')
                                ->label(false)
                                ->maxLength(255)
                                ->readOnly()
                                ->placeholder('Req. N° - Año')
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hintActions([
                                    Forms\Components\Actions\Action::make('search_requirement')
                                        ->label('Buscar')
                                        ->icon('heroicon-m-magnifying-glass')
                                        ->color('primary')
                                        ->size('sm')
                                        ->modalHeading('Buscar Requerimiento en SILUCIA')
                                        ->modalDescription('Selecciona el año e ingresa el número del requerimiento para buscar en el sistema SILUCIA')
                                        ->modalSubmitActionLabel('Seleccionar')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->modalWidth('2xl')
                                        ->form(self::createRequirementSearchForm())
                                        ->action(function (array $data, Forms\Set $set, $record) {
                                            self::handleRequirementSelection($data, $set, $record, 'seleccionado');
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            // Solo mostrar el hintAction cuando NO hay datos de la API
                                            $apiData = $get('s1Stage.requirement_api_data');
                                            return empty($apiData);
                                        }),

                                    // Acción 2: Cambiar requerimiento (cuando SÍ hay datos)
                                    Forms\Components\Actions\Action::make('change_requirement')
                                        ->label(false)
                                        ->icon('heroicon-m-pencil-square')
                                        ->iconSize('lg')
                                        ->tooltip('Cambiar Requerimiento')
                                        ->color('warning')
                                        ->modalHeading('Cambiar Requerimiento')
                                        ->modalDescription('Selecciona un nuevo requerimiento para reemplazar el actual')
                                        ->modalSubmitActionLabel('Cambiar')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->modalWidth('2xl')
                                        ->form(self::createRequirementSearchForm('Información del Nuevo Requerimiento', 'blue'))
                                        ->action(function (array $data, Forms\Set $set, $record) {
                                            self::handleRequirementSelection($data, $set, $record, 'cambiado');
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            // Solo mostrar cuando SÍ hay datos de la API
                                            $apiData = $get('s1Stage.requirement_api_data');
                                            return ! empty($apiData);
                                        }),
                                ])

                                ->hint(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && ! empty($apiData)) {
                                        return 'Detalle ->';
                                    }

                                    return null;
                                })
                                ->hintIcon(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && ! empty($apiData)) {
                                        return 'heroicon-m-information-circle';
                                    }

                                    return null;
                                })
                                ->hintColor(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && ! empty($apiData)) {
                                        return 'info';
                                    }

                                    return null;
                                })
                                ->hintIconTooltip(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && ! empty($apiData)) {
                                        return 'Procedimiento: '.$apiData['desprocedim'].' | Síntesis: '.$apiData['sintesis'];
                                    }

                                    return null;
                                })
                                ->helperText(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');

                                    if ($apiData && ! empty($apiData)) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="text-xs font-semibold">'
                                            .'T. Segmentación: '.$apiData['descripcion_segmentacion'].
                                            '</span>'
                                        );
                                    }

                                    return null;
                                }),

                            // Campo de fecha con validación de plazos
                            DatePicker::make('s1Stage.request_presentation_date')
                                ->label('F. de Presentación')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->live()
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.request_presentation_date'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.request_presentation_date'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.request_presentation_date'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.request_presentation_date'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.request_presentation_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 2: INDAGACIÓN DE MERCADO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Indagación de Mercado'))
                        ->compact()
                        ->schema([
                            TextInput::make('s1Stage.market_indagation_doc')
                                ->label(false)
                                ->placeholder('Documento/Ref.')
                                ->maxLength(255)
                                ->visible(fn ($record) => $record?->s1Stage),

                            DatePicker::make('s1Stage.market_indagation_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.market_indagation_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.market_indagation_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.market_indagation_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.market_indagation_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.market_indagation_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.market_indagation_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: CERTIFICACIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Certificación'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.with_certification')
                                ->label(function (Forms\Get $get) {
                                    return $get('s1Stage.with_certification') ? 'Si tiene.' : 'No tiene';
                                })
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(false)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        // Si selecciona que SÍ tiene certificación → limpiar el motivo
                                        $set('s1Stage.no_certification_reason', null);
                                    } else {
                                        // Si selecciona que NO tiene certificación → limpiar campos de certificación
                                        $set('s1Stage.certification_amount', null);
                                        $set('s1Stage.certification_date', null);
                                        $set('s1Stage.certification_file', null);
                                    }
                                }),

                            // Campos para cuando SÍ tiene certificación
                            TextInput::make('s1Stage.certification_amount')
                                ->label(false)
                                ->numeric()
                                ->prefix('S/')
                                ->placeholder('0.00')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_certification'))
                                ->hintActions([
                                    // Acción 1: Subir archivo
                                    Forms\Components\Actions\Action::make('upload_certification_file')
                                        ->label('Subir')
                                        ->icon('heroicon-m-cloud-arrow-up')
                                        ->color('primary')
                                        ->size('sm')
                                        ->tooltip('Subir archivo de certificación')
                                        ->modalHeading('Subir Archivo de Certificación')
                                        ->modalDescription('Selecciona el archivo de certificación para adjuntar')
                                        ->modalSubmitActionLabel('Subir')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->modalWidth('md')
                                        ->form([
                                            Forms\Components\FileUpload::make('file')
                                                ->label('Archivo')
                                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                                ->maxSize(10240) // 10MB
                                                ->directory('tenders/certifications')
                                                ->visibility('private')
                                                ->required()
                                                ->helperText('Formatos permitidos: PDF, JPG, PNG. Tamaño máximo: 10MB'),
                                        ])
                                        ->action(function (array $data, Forms\Set $set, $record) {
                                            self::handleCertificationFileUpload($data, $set, $record);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return empty($get('s1Stage.certification_file'));
                                        }),

                                    // Acción 2: Ver archivo
                                    Forms\Components\Actions\Action::make('view_certification_file')
                                        ->label('Ver')
                                        ->icon('heroicon-m-eye')
                                        ->color('info')
                                        ->size('sm')
                                        ->tooltip('Ver archivo de certificación')
                                        ->action(function (Forms\Get $get) {
                                            self::handleCertificationFileView($get);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return ! empty($get('s1Stage.certification_file'));
                                        }),

                                    // Acción 3: Eliminar archivo
                                    Forms\Components\Actions\Action::make('remove_certification_file')
                                        ->label('Eliminar')
                                        ->icon('heroicon-m-trash')
                                        ->color('danger')
                                        ->size('sm')
                                        ->tooltip('Eliminar archivo de certificación')
                                        ->requiresConfirmation()
                                        ->modalHeading('Eliminar Archivo de Certificación')
                                        ->modalDescription(function (Forms\Get $get) {
                                            return '¿Estás seguro de que quieres eliminar el archivo de certificación: '.$get('s1Stage.certification_file');
                                        })
                                        ->modalSubmitActionLabel('Sí, eliminar')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->action(function (Forms\Set $set, $record) {
                                            self::handleCertificationFileRemove($set, $record);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return ! empty($get('s1Stage.certification_file'));
                                        }),
                                ]),

                            DatePicker::make('s1Stage.certification_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_certification'))
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.certification_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.certification_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.certification_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.certification_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.certification_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.certification_date')),

                            // Campo para cuando NO tiene certificación
                            TextInput::make('s1Stage.no_certification_reason')
                                ->label(false)
                                ->placeholder('Motivo?')
                                ->maxLength(255)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => $get('s1Stage.with_certification')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 4: PREVISIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Previsión'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.with_provision')
                                ->label(function (Forms\Get $get) {
                                    return $get('s1Stage.with_provision') ? 'Si tiene.' : 'No tiene';
                                })
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(false)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (! $state) {
                                        // Si selecciona que NO tiene previsión → limpiar todos los campos
                                        $set('s1Stage.provision_amount', null);
                                        $set('s1Stage.provision_date', null);
                                        $set('s1Stage.provision_file', null);
                                    }
                                }),

                            TextInput::make('s1Stage.provision_amount')
                                ->label(false)
                                ->numeric()
                                ->prefix('S/')
                                ->placeholder('0.00')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_provision'))
                                ->hintActions([
                                    // Acción 1: Subir archivo
                                    Forms\Components\Actions\Action::make('upload_provision_file')
                                        ->label('Subir')
                                        ->icon('heroicon-m-cloud-arrow-up')
                                        ->color('primary')
                                        ->size('sm')
                                        ->modalHeading('Subir Archivo de Previsión')
                                        ->modalDescription('Selecciona el archivo de previsión para adjuntar')
                                        ->modalSubmitActionLabel('Subir')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->modalWidth('md')
                                        ->form([
                                            Forms\Components\FileUpload::make('file')
                                                ->label('Archivo')
                                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                                ->maxSize(10240) // 10MB
                                                ->directory('tenders/provisions')
                                                ->visibility('private')
                                                ->required()
                                                ->helperText('Formatos permitidos: PDF, JPG, PNG. Tamaño máximo: 10MB'),
                                        ])
                                        ->action(function (array $data, Forms\Set $set, $record) {
                                            self::handleProvisionFileUpload($data, $set, $record);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return empty($get('s1Stage.provision_file'));
                                        }),

                                    // Acción 2: Ver archivo
                                    Forms\Components\Actions\Action::make('view_provision_file')
                                        ->label('Ver')
                                        ->icon('heroicon-m-eye')
                                        ->color('info')
                                        ->size('sm')
                                        ->tooltip('Ver archivo de previsión')
                                        ->action(function (Forms\Get $get) {
                                            self::handleProvisionFileView($get);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return ! empty($get('s1Stage.provision_file'));
                                        }),

                                    // Acción 3: Eliminar archivo
                                    Forms\Components\Actions\Action::make('remove_provision_file')
                                        ->label('Eliminar')
                                        ->icon('heroicon-m-trash')
                                        ->color('danger')
                                        ->size('sm')
                                        ->tooltip('Eliminar archivo de previsión')
                                        ->requiresConfirmation()
                                        ->modalHeading('Eliminar Archivo de Previsión')
                                        ->modalDescription(function (Forms\Get $get) {
                                            return '¿Estás seguro de que quieres eliminar el archivo de previsión: '.$get('s1Stage.provision_file');
                                        })
                                        ->modalSubmitActionLabel('Sí, eliminar')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->action(function (Forms\Set $set, $record) {
                                            self::handleProvisionFileRemove($set, $record);
                                        })
                                        ->visible(function (Forms\Get $get, $component) {
                                            // Ocultar si el campo está en modo readonly (ViewAction)
                                            if ($component->isDisabled()) {
                                                return false;
                                            }
                                            return ! empty($get('s1Stage.provision_file'));
                                        }),
                                ]),

                            DatePicker::make('s1Stage.provision_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_provision'))
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.provision_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.provision_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.provision_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.provision_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.provision_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.provision_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 5: APROBACIÓN DEL EXPEDIENTE DE CONTRATACIÓN
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Aprobación del Expediente', 'de Contratación'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s1Stage.approval_expedient_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.approval_expedient_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.approval_expedient_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.approval_expedient_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.approval_expedient_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.approval_expedient_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.approval_expedient_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 6: DESIGNACIÓN DEL COMITÉ DE SELECCIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Designación del Comité', 'de Selección'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.apply_selection_committee')
                                ->label(fn (Forms\Get $get) => $get('s1Stage.apply_selection_committee') ? 'Si aplica.' : 'No aplica.')
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(true)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage),

                            DatePicker::make('s1Stage.selection_committee_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.apply_selection_committee'))
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.selection_committee_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.selection_committee_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.selection_committee_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.selection_committee_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.selection_committee_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.selection_committee_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 7: ELABORACIÓN DE BASES ADMINISTRATIVAS
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Elaboración de Bases Administrativas'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s1Stage.administrative_bases_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.administrative_bases_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.administrative_bases_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.administrative_bases_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.administrative_bases_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.administrative_bases_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.administrative_bases_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 8: APROBACIÓN DE BASES ADMINISTRATIVAS FORMATO 2
                    // ========================================================================
                    Section::make()
                        ->description(new HtmlString(
                            '<h4 class="text-center font-bold text-xs">Aprobación de Bases Administrativas<br>Formato 2 y Expediente</h4>'
                        ))
                        ->compact()
                        ->schema([
                            DatePicker::make('s1Stage.approval_expedient_format_2')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->live()
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.approval_expedient_format_2', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.approval_expedient_format_2', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.approval_expedient_format_2', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.approval_expedient_format_2', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.approval_expedient_format_2', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S1', 's1Stage.approval_expedient_format_2')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 9: CÁLCULO DE TOTALES DE DÍAS
                    // ========================================================================
                    // Section::make()
                    //     ->description(new HtmlString(
                    //         '<h2 class="text-center font-bold text-3xl">TOTAL DE DIAS</h2>'
                    //     ))
                    //     ->compact()
                    //     ->schema([
                    //         // Usar componentes compartidos para cálculos
                    //         DateCalculations::createCalendarDaysPlaceholder(
                    //             's1Stage.request_presentation_date',
                    //             's1Stage.approval_expedient_format_2',
                    //             'total_days'
                    //         ),

                    //         DateCalculations::createBusinessDaysPlaceholder(
                    //             's1Stage.request_presentation_date',
                    //             's1Stage.approval_expedient_format_2',
                    //             'total_business_days'
                    //         ),
                    //     ])->columnSpan(2),

                ])->visible(fn ($record) => $record?->s1Stage),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab S1 Preparatory
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => fn ($record) => self::getTabLabel($record),
            'icon' => 'heroicon-m-clipboard-document-list',
            'extraAttributes' => ['style' => 'white-space: pre-line; padding-top: 0.5rem; text-align: center; line-height: 1.2;'],
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🏷️ Genera el label del tab con tooltip en el badge (TAREA 2)
     */
    private static function getTabLabel($record): HtmlString
    {
        $baseLabel = '<span class="font-bold text-lg">1.</span> <span class="text-sm font-medium">Act. Preparatorias</span>';
        
        if (!$record?->s1Stage) {
            return new HtmlString($baseLabel);
        }
        
        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, 'S1');
        $tooltip = \App\Filament\Resources\TenderResource\Components\Shared\StageHelpers::getStageBadgeTooltip($record, 'S1');
        
        $badgeWithTooltip = '<span title="' . htmlspecialchars($tooltip) . '" class="cursor-help font-semibold text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">' . $progress . '%</span>';
        
        return new HtmlString($baseLabel . $badgeWithTooltip);
    }

    /**
     * 🔧 Obtiene las opciones de configuración para toggles
     *
     * @return array Configuración de toggles
     */
    public static function getToggleConfig(): array
    {
        return [
            'certification' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => false,
            ],
            'provision' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => false,
            ],
            'selection_committee' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => true,
            ],
        ];
    }

    /**
     * 📅 Obtiene la configuración de campos de fecha con iconos
     *
     * @return array Configuración de campos de fecha
     */
    public static function getDateFieldConfig(): array
    {
        return [
            'request_presentation_date' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'info',
                'live' => true,
            ],
            'approval_expedient_format_2' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'success',
                'live' => true,
            ],
        ];
    }

    /**
     * 📋 Obtiene los plazos legales para cada sección
     *
     * @return array Plazos legales por sección
     */
    public static function getLegalTimeframes(): array
    {
        return [
            'approval_expedient' => '02 días hábiles',
            'administrative_bases' => '02 días hábiles',
            'approval_expedient_format_2' => '01 día hábil',
            'selection_committee' => '01 día hábil, segun Ley',
        ];
    }

    /**
     * ✅ Valida si una etapa S1 está completa
     *
     * @param  array  $s1Data  Datos de la etapa S1
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s1Data): bool
    {
        // Usar configuración centralizada de StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S1');
        
        // Obtener todos los campos críticos de la configuración
        $requiredFields = $config['critical_fields'];
        
        // Validar que todos los campos críticos estén completos
        foreach ($requiredFields as $field) {
            if (empty($s1Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S1 usando configuración centralizada
     *
     * @param  array  $s1Data  Datos de la etapa S1
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s1Data): int
    {
        // ✅ Usar configuración centralizada del StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S1');
        $allRelevantFields = array_merge(
            $config['critical_fields'],
            $config['optional_fields']
        );

        if (empty($allRelevantFields)) {
            return 0;
        }

        $completedFields = 0;
        foreach ($allRelevantFields as $field) {
            if (!empty($s1Data[$field])) {
                $completedFields++;
            }
        }

        return (int) round(($completedFields / count($allRelevantFields)) * 100);
    }

    /**
     * 🎯 MÉTODOS HELPER REUTILIZABLES
     */

    /**
     * Obtiene las opciones de años para los formularios
     */
    private static function getYearOptions(): array
    {
        return [
            '2023' => '2023',
            '2024' => '2024',
            '2025' => '2025',
            '2026' => '2026',
        ];
    }

    /**
     * Crea el formulario de búsqueda de requerimientos reutilizable
     */
    private static function createRequirementSearchForm(string $title = 'Información del Requerimiento', string $bgColor = 'green'): array
    {
        return [
            Grid::make(10)
                ->schema([
                    Forms\Components\TextInput::make('numero')
                        ->label('N° Req.')
                        ->placeholder('Ej: 4618')
                        ->required()
                        ->autofocus()
                        ->numeric()
                        ->maxLength(10)
                        ->inlineLabel()
                        ->columnSpan(4),

                    Forms\Components\Select::make('anio')
                        ->label('Año')
                        ->options(self::getYearOptions())
                        ->default(now()->year)
                        ->required()
                        ->placeholder('Selecciona el año')
                        ->inlineLabel()
                        ->columnSpan(4),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('search_in_modal')
                            ->label('Buscar')
                            ->icon('heroicon-m-magnifying-glass')
                            ->color('info')
                            ->size('sm')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                self::handleRequirementSearch($get, $set);
                            }),
                    ]),
                ]),

            // Mostrar información del requerimiento encontrado
            Forms\Components\Placeholder::make('requirement_info')
                ->label($title)
                ->content(function (Forms\Get $get) use ($bgColor) {
                    $apiData = $get('s1Stage.requirement_api_data');
                    if ($apiData) {
                        $colorClass = $bgColor === 'blue' ? 'blue' : 'green';

                        return new \Illuminate\Support\HtmlString(
                            '<div class="bg-'.$colorClass.'-50 border border-'.$colorClass.'-200 rounded-lg p-4">'.
                            '<h4 class="font-semibold text-'.$colorClass.'-800 mb-2">Requerimiento Encontrado:</h4>'.
                            '<p><strong>Número:</strong> '.$apiData['numero'].'</p>'.
                            '<p><strong>Año:</strong> '.$apiData['anio'].'</p>'.
                            '<p><strong>Procedimiento:</strong> '.$apiData['desprocedim'].'</p>'.
                            '<p><strong>T. Segmentación:</strong> '.$apiData['descripcion_segmentacion'].'</p>'.
                            '<p><strong>Síntesis:</strong> '.$apiData['sintesis'].'</p>'.
                            '<p><strong>Meta:</strong> '.$apiData['codmeta'].' - '.$apiData['desmeta'].'</p>'.
                            '</div>'
                        );
                    }

                    return 'Realiza una búsqueda para ver la información del requerimiento';
                })
                ->visible(fn (Forms\Get $get) => ! empty($get('s1Stage.requirement_api_data'))),
        ];
    }

    /**
     * Maneja la lógica de búsqueda de requerimientos
     */
    private static function handleRequirementSearch(Forms\Get $get, Forms\Set $set): void
    {
        $numero = $get('numero');
        $anio = $get('anio');

        if (empty($numero) || empty($anio)) {
            \Filament\Notifications\Notification::make()
                ->title('Campos requeridos')
                ->body('Por favor completa el número y año del requerimiento')
                ->warning()
                ->send();

            return;
        }

        // Buscar en la API
        $requirement = \App\Services\RequirementApiService::searchRequirement($numero, $anio);

        if ($requirement) {
            // Formatear datos
            $formattedData = \App\Services\RequirementApiService::formatRequirementData($requirement);

            // Mostrar resultado en el modal
            \Filament\Notifications\Notification::make()
                ->title('✅ Requerimiento encontrado')
                ->body('Se encontró el requerimiento: '.$formattedData['sintesis'])
                ->success()
                ->send();

            // Actualizar campos del formulario principal
            $set('s1Stage.requirement_api_data', $formattedData);
            $set('s1Stage.request_presentation_doc', 'Req. '.$formattedData['numero'].' - '.$formattedData['anio']);

        } else {
            // Mostrar notificación de error
            \Filament\Notifications\Notification::make()
                ->title('❌ Requerimiento no encontrado')
                ->body('No se encontró ningún requerimiento con el número '.$numero.' del año '.$anio)
                ->danger()
                ->send();
        }
    }

    /**
     * Maneja la acción de selección/cambio de requerimiento
     */
    private static function handleRequirementSelection(array $data, Forms\Set $set, $record, string $actionType = 'seleccionado'): void
    {
        $numero = $data['numero'];
        $anio = $data['anio'];

        if (empty($numero) || empty($anio)) {
            \Filament\Notifications\Notification::make()
                ->title('Campos requeridos')
                ->body('Por favor completa el número y año del requerimiento')
                ->warning()
                ->send();

            return;
        }

        // Buscar en la API
        $requirement = \App\Services\RequirementApiService::searchRequirement($numero, $anio);

        if ($requirement) {
            // Formatear datos
            $formattedData = \App\Services\RequirementApiService::formatRequirementData($requirement);


            // =========================================
            // NUEVO: Crear o buscar META
            // =========================================

            // Ver todo el contenido formateado
            // Log::info('Requirement formateado:', $formattedData);

            // Crear o buscar meta
            $meta = Meta::firstOrCreate(
                [
                    'codmeta' => $formattedData['codmeta'] ?? '',
                    'anio' => $formattedData['anio'] ?? '',
                ],
                [
                    'nombre' => $formattedData['desprocedim'] ?? '',
                    'desmeta' => $formattedData['desmeta'] ?? '',
                    'cui' => $formattedData['prod_proy'] ?? '',
                    'snapshot' => $formattedData ?? [],
                ]
            );

            // Actualizar campos del formulario principal
            $set('s1Stage.requirement_api_data', $formattedData);
            $set('s1Stage.request_presentation_doc', 'Req. '.$formattedData['numero'].' - '.$formattedData['anio']);

            // Forzar el guardado en la base de datos
            if ($record) {
                $record->s1Stage = array_merge($record->s1Stage ?? [], [
                    'requirement_api_data' => $formattedData,
                    'request_presentation_doc' => 'Req. '.$formattedData['numero'].' - '.$formattedData['anio'],
                ]);

                // NUEVO: asignar meta al tender
                $record->meta_id = $meta->id;

                $record->save();
            }

            // Mostrar notificación de éxito
            \Filament\Notifications\Notification::make()
                ->title('Requerimiento '.$actionType)
                ->body('Se ha '.$actionType.' el requerimiento: '.$formattedData['sintesis'])
                ->success()
                ->send();

        } else {
            // Mostrar notificación de error
            \Filament\Notifications\Notification::make()
                ->title('Requerimiento no encontrado')
                ->body('No se encontró ningún requerimiento con el número '.$numero.' del año '.$anio)
                ->danger()
                ->send();
        }
    }

    /**
     * 🎯 MÉTODOS GENÉRICOS PARA MANEJO DE ARCHIVOS
     */

    /**
     * Maneja la subida de archivos de forma genérica
     */
    private static function handleFileUpload(array $data, Forms\Set $set, $record, string $fieldName, string $fileType): void
    {
        if (empty($data['file'])) {
            \Filament\Notifications\Notification::make()
                ->title('Archivo requerido')
                ->body('Por favor selecciona un archivo para subir')
                ->warning()
                ->send();

            return;
        }

        // Actualizar el campo con la ruta del archivo
        $set("s1Stage.{$fieldName}", $data['file']);

        // Forzar el guardado en la base de datos
        if ($record) {
            $record->s1Stage = array_merge($record->s1Stage ?? [], [
                $fieldName => $data['file'],
            ]);
            $record->save();
        }

        // Mostrar notificación de éxito
        \Filament\Notifications\Notification::make()
            ->title('Archivo subido')
            ->body("El archivo de {$fileType} se ha subido correctamente")
            ->success()
            ->send();
    }

    /**
     * Maneja la subida de archivos de previsión
     */
    private static function handleProvisionFileUpload(array $data, Forms\Set $set, $record): void
    {
        self::handleFileUpload($data, $set, $record, 'provision_file', 'previsión');
    }

    /**
     * Maneja la visualización de archivos de forma genérica
     */
    private static function handleFileView(Forms\Get $get, string $fieldName, string $fileType): void
    {
        $filePath = $get("s1Stage.{$fieldName}");

        if (empty($filePath)) {
            \Filament\Notifications\Notification::make()
                ->title('No hay archivo')
                ->body("No hay archivo de {$fileType} para mostrar")
                ->warning()
                ->send();

            return;
        }

        // Generar URL del archivo
        $fileUrl = \Illuminate\Support\Facades\Storage::url($filePath);

        // Abrir archivo en nueva pestaña
        \Filament\Notifications\Notification::make()
            ->title('Abriendo archivo')
            ->body('El archivo se abrirá en una nueva pestaña')
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('open')
                    ->label('Abrir archivo')
                    ->url($fileUrl, shouldOpenInNewTab: true)
                    ->button(),
            ])
            ->send();
    }

    /**
     * Maneja la visualización de archivos de previsión
     */
    private static function handleProvisionFileView(Forms\Get $get): void
    {
        self::handleFileView($get, 'provision_file', 'previsión');
    }

    /**
     * Maneja la eliminación de archivos de forma genérica
     */
    private static function handleFileRemove(Forms\Set $set, $record, string $fieldName, string $fileType): void
    {
        // Obtener la ruta del archivo actual
        $currentFilePath = null;
        if ($record && $record->s1Stage) {
            $currentFilePath = $record->s1Stage[$fieldName] ?? null;
        }

        // Eliminar archivo físico si existe
        if ($currentFilePath && \Illuminate\Support\Facades\Storage::exists($currentFilePath)) {
            try {
                \Illuminate\Support\Facades\Storage::delete($currentFilePath);
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title('Error al eliminar archivo')
                    ->body('No se pudo eliminar el archivo físico: '.$e->getMessage())
                    ->warning()
                    ->send();
            }
        }

        // Limpiar el campo del archivo
        $set("s1Stage.{$fieldName}", null);

        // Forzar el guardado en la base de datos
        if ($record) {
            $record->s1Stage = array_merge($record->s1Stage ?? [], [
                $fieldName => null,
            ]);
            $record->save();
        }

        // Mostrar notificación de éxito
        \Filament\Notifications\Notification::make()
            ->title('Archivo eliminado')
            ->body("El archivo de {$fileType} se ha eliminado correctamente")
            ->success()
            ->send();
    }

    /**
     * Maneja la eliminación de archivos de previsión
     */
    private static function handleProvisionFileRemove(Forms\Set $set, $record): void
    {
        self::handleFileRemove($set, $record, 'provision_file', 'previsión');
    }

    /**
     * Maneja la subida de archivos de certificación
     */
    private static function handleCertificationFileUpload(array $data, Forms\Set $set, $record): void
    {
        self::handleFileUpload($data, $set, $record, 'certification_file', 'certificación');
    }

    /**
     * Maneja la visualización de archivos de certificación
     */
    private static function handleCertificationFileView(Forms\Get $get): void
    {
        self::handleFileView($get, 'certification_file', 'certificación');
    }

    /**
     * Maneja la eliminación de archivos de certificación
     */
    private static function handleCertificationFileRemove(Forms\Set $set, $record): void
    {
        self::handleFileRemove($set, $record, 'certification_file', 'certificación');
    }
}
