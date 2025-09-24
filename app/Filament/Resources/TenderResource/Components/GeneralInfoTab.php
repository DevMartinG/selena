<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * 📋 COMPONENTE: TAB INFORMACIÓN GENERAL
 *
 * Este componente maneja toda la información básica del Tender
 * en el tab "Info. General" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Identificación del proceso (nomenclatura, tipo de proceso)
 * - Información financiera (moneda, valor estimado)
 * - Objeto de contratación y descripción
 * - Estado actual del procedimiento
 * - Observaciones y comité de selección
 * - Validación de nomenclatura duplicada
 *
 * DISTRIBUCIÓN VISUAL:
 * - Panel izquierdo (60%): Información Principal
 * - Panel derecho (40%): Estado, Observaciones y Comité
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab General Info
 * - Mantiene toda la funcionalidad original
 */
class GeneralInfoTab
{
    /**
     * 🎯 Crea el schema completo del tab General Info
     *
     * @return array Array de componentes para el schema del tab
     */
    public static function getSchema(): array
    {
        return [
            Grid::make(5)
                ->schema([
                    // ========================================================================
                    // 📊 PANEL IZQUIERDO: INFORMACIÓN PRINCIPAL (60% = 3/5)
                    // ========================================================================
                    Fieldset::make('Información Principal')
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    // ========================================================================
                                    // 🏷️ IDENTIFICACIÓN DEL PROCESO
                                    // ========================================================================
                                    TextInput::make('identifier')
                                        ->label('Nomenclatura')
                                        ->required()
                                        ->maxLength(255)
                                        ->autofocus()
                                        ->columnSpan(7)
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Validar nomenclatura duplicada
                                            $normalized = Tender::normalizeIdentifier($state);

                                            $isDuplicate = Tender::query()
                                                ->where('code_full', $normalized)
                                                ->when($get('id'), fn ($query, $id) => $query->where('id', '!=', $id))
                                                ->exists();

                                            if ($isDuplicate) {
                                                Notification::make()
                                                    ->title('Nomenclatura duplicada')
                                                    ->icon('heroicon-s-exclamation-triangle')
                                                    ->warning()
                                                    ->duration(5000)
                                                    ->send();
                                            }
                                        }),

                                    Select::make('process_type')
                                        ->label('Tipo de Proceso')
                                        ->options(\App\Models\ProcessType::pluck('description_short_type', 'description_short_type'))
                                        ->required()
                                        ->columnSpan(5),

                                    // ========================================================================
                                    // 💰 INFORMACIÓN FINANCIERA
                                    // ========================================================================
                                    Select::make('currency_name')
                                        ->label('Moneda')
                                        ->options([
                                            'PEN' => 'Soles (PEN)',
                                            'USD' => 'Dólares (USD)',
                                            'EUR' => 'Euros (EUR)',
                                        ])
                                        ->required()
                                        ->default('PEN')
                                        ->columnSpan(3),

                                    TextInput::make('estimated_referenced_value')
                                        ->label('Valor Ref. / Valor Estimado')
                                        ->numeric()
                                        ->prefix(fn (Forms\Get $get) => match ($get('currency_name')) {
                                            'PEN' => 'S/',
                                            'USD' => '$',
                                            'EUR' => '€',
                                            default => 'S/',
                                        })
                                        ->step(0.01)
                                        ->minValue(0)
                                        ->required()
                                        ->columnSpan(4),

                                    Select::make('contract_object')
                                        ->label('Objeto de Contratación')
                                        ->required()
                                        ->options([
                                            'Bien' => 'Bien',
                                            'Consultoría de Obra' => 'Consultoría de Obra',
                                            'Obra' => 'Obra',
                                            'Servicio' => 'Servicio',
                                        ])
                                        ->placeholder('[Seleccione]')
                                        ->columnSpan(5),

                                    // ========================================================================
                                    // 📝 DESCRIPCIÓN DEL OBJETO
                                    // ========================================================================
                                    Textarea::make('object_description')
                                        ->label('Descripción del Objeto')
                                        ->required()
                                        ->rows(4)
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpan(3), // 60% del espacio

                    // ========================================================================
                    // 📊 PANEL DERECHO: ESTADO, OBSERVACIONES Y COMITÉ (40% = 2/5)
                    // ========================================================================
                    Fieldset::make('Estado, Observaciones y Comité')
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    // ========================================================================
                                    // 🎯 ESTADO ACTUAL DEL PROCEDIMIENTO
                                    // ========================================================================
                                    Select::make('tender_status_id')
                                        ->label('Estado Actual')
                                        ->options(\App\Models\TenderStatus::validForForm()->pluck('name', 'id'))
                                        ->columnSpanFull()
                                        ->required()
                                        ->placeholder('Seleccione el estado'),

                                    // ========================================================================
                                    // 📝 OBSERVACIONES Y COMITÉ DE SELECCIÓN
                                    // ========================================================================
                                    Textarea::make('observation')
                                        ->label('Observaciones')
                                        ->rows(3)
                                        ->columnSpanFull(),

                                    Textarea::make('selection_comittee')
                                        ->label('OEC/ Comité de Selección')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpan(2), // 40% del espacio
                ]),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab General Info
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => 'Info. General',
            'icon' => 'heroicon-m-clipboard-document',
            'iconPosition' => \Filament\Support\Enums\IconPosition::Before,
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🔧 Obtiene las opciones de moneda para el formulario
     *
     * @return array Opciones de moneda
     */
    public static function getCurrencyOptions(): array
    {
        return [
            'PEN' => 'Soles (PEN)',
            'USD' => 'Dólares (USD)',
            'EUR' => 'Euros (EUR)',
        ];
    }

    /**
     * 🔧 Obtiene las opciones de objeto de contratación
     *
     * @return array Opciones de objeto de contratación
     */
    public static function getContractObjectOptions(): array
    {
        return [
            'Bien' => 'Bien',
            'Consultoría de Obra' => 'Consultoría de Obra',
            'Obra' => 'Obra',
            'Servicio' => 'Servicio',
        ];
    }

    /**
     * 💰 Obtiene el prefijo de moneda según la moneda seleccionada
     *
     * @param  string  $currency  Código de moneda
     * @return string Prefijo de moneda
     */
    public static function getCurrencyPrefix(string $currency): string
    {
        return match ($currency) {
            'PEN' => 'S/',
            'USD' => '$',
            'EUR' => '€',
            default => 'S/',
        };
    }

    /**
     * ✅ Valida si una nomenclatura está duplicada
     *
     * @param  string  $identifier  Nomenclatura a validar
     * @param  int|null  $excludeId  ID a excluir de la validación (para edición)
     * @return bool True si está duplicada
     */
    public static function isIdentifierDuplicate(string $identifier, ?int $excludeId = null): bool
    {
        $normalized = Tender::normalizeIdentifier($identifier);

        return Tender::query()
            ->where('code_full', $normalized)
            ->when($excludeId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists();
    }

    /**
     * 🔔 Crea una notificación de nomenclatura duplicada
     */
    public static function showDuplicateNotification(): void
    {
        Notification::make()
            ->title('Nomenclatura duplicada')
            ->icon('heroicon-s-exclamation-triangle')
            ->warning()
            ->duration(5000)
            ->send();
    }
}
