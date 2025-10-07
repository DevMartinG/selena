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
                                    // ========================================================================
                                    // 🔍 TOGGLE PARA ELEGIR MODO DE CREACIÓN
                                    // ========================================================================
                                    Forms\Components\Toggle::make('with_identifier')
                                        ->label('Tiene nomenclatura?')
                                        ->default(true)
                                        ->live()
                                        ->helperText('Activar si el procedimiento tiene nomenclatura válida')
                                        ->columnSpanFull(),

                                    // ========================================================================
                                    // 🔍 BÚSQUEDA EN SEACE Y AUTOMÁTICO COMPLETADO
                                    // ========================================================================
                                    Select::make('seace_tender_id')
                                        ->label('Buscar procedimiento')
                                        ->searchable()
                                        ->getSearchResultsUsing(function (string $search): array {
                                            // ========================================
                                            // BÚSQUEDA INTELIGENTE POR PALABRAS CLAVE
                                            // Similar a la búsqueda de Filament ListResources
                                            // ========================================
                                            
                                            if (empty(trim($search))) {
                                                return [];
                                            }
                                            
                                            // Dividir la búsqueda en palabras clave
                                            $keywords = array_filter(
                                                array_map('trim', explode(' ', strtoupper($search))),
                                                fn($keyword) => !empty($keyword)
                                            );
                                            
                                            if (empty($keywords)) {
                                                return [];
                                            }
                                            
                                            // Construir query con múltiples criterios
                                            $query = \App\Models\SeaceTender::query();
                                            
                                            foreach ($keywords as $keyword) {
                                                $query->where(function ($subQuery) use ($keyword) {
                                                    $subQuery
                                                        ->where('identifier', 'like', "%{$keyword}%")
                                                        ->orWhere('entity_name', 'like', "%{$keyword}%")
                                                        ->orWhere('contract_object', 'like', "%{$keyword}%")
                                                        ->orWhere('object_description', 'like', "%{$keyword}%")
                                                        ->orWhere('code_short_type', 'like', "%{$keyword}%")
                                                        ->orWhere('code_type', 'like', "%{$keyword}%");
                                                });
                                            }
                                            
                                            // Obtener resultados sin ordenar primero
                                            $results = $query->get();
                                            
                                            // Aplicar scoring inteligente
                                            $scoredResults = $results->map(function ($item) use ($keywords, $search) {
                                                $score = 0;
                                                $identifier = strtoupper($item->identifier);
                                                $entityName = strtoupper($item->entity_name);
                                                $contractObject = strtoupper($item->contract_object);
                                                
                                                // Scoring por coincidencias exactas en identifier
                                                foreach ($keywords as $keyword) {
                                                    if (str_contains($identifier, $keyword)) {
                                                        $score += 100; // Máxima prioridad
                                                        
                                                        // Bonus si coincide al inicio
                                                        if (str_starts_with($identifier, $keyword)) {
                                                            $score += 50;
                                                        }
                                                    }
                                                    
                                                    // Scoring por otros campos
                                                    if (str_contains($entityName, $keyword)) {
                                                        $score += 30;
                                                    }
                                                    if (str_contains($contractObject, $keyword)) {
                                                        $score += 20;
                                                    }
                                                }
                                                
                                                // Bonus por coincidencia completa de búsqueda
                                                if (str_contains($identifier, strtoupper($search))) {
                                                    $score += 200;
                                                }
                                                
                                                return [
                                                    'item' => $item,
                                                    'score' => $score
                                                ];
                                            })
                                            ->sortByDesc('score')
                                            ->take(50)
                                            ->pluck('item');
                                            
                                            // $scoredResults ahora contiene objetos SeaceTender
                                            return $scoredResults->mapWithKeys(fn ($item) => [
                                                $item->id => "{$item->identifier} - {$item->estimated_referenced_value}"
                                            ])->toArray();
                                        })
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $seaceTender = \App\Models\SeaceTender::find($state);
                                                if ($seaceTender) {
                                                    // ========================================
                                                    // AUTOMÁTICO COMPLETADO DE CAMPOS COMUNES
                                                    // ========================================
                                                    $set('entity_name', $seaceTender->entity_name);
                                                    $set('process_type', $seaceTender->process_type);
                                                    $set('contract_object', $seaceTender->contract_object);
                                                    $set('object_description', $seaceTender->object_description);
                                                    $set('estimated_referenced_value', $seaceTender->estimated_referenced_value);
                                                    $set('currency_name', $seaceTender->currency_name);
                                                    $set('tender_status_id', $seaceTender->tender_status_id);
                                                    
                                                    // Establecer identifier del SeaceTender seleccionado
                                                    $set('identifier', $seaceTender->identifier);
                                                    
                                                    // Regenerar campos derivados automáticamente
                                                    try {
                                                        $codeInfo = \App\Models\Tender::extractCodeInfo($seaceTender->identifier);
                                                        $set('code_short_type', $codeInfo['code_short_type']);
                                                        $set('code_type', $codeInfo['code_type']);
                                                        
                                                        $cleanIdentifier = \App\Models\Tender::normalizeIdentifier($seaceTender->identifier);
                                                        if (preg_match('/\b(20\d{2})\b/', $cleanIdentifier, $yearMatch)) {
                                                            $set('code_year', $yearMatch[1]);
                                                            
                                                            $beforeYear = explode($yearMatch[1], $cleanIdentifier)[0] ?? '';
                                                            $segmentsBeforeYear = array_filter(explode('-', $beforeYear));
                                                            $set('code_sequence', \App\Models\Tender::extractLastNumeric($segmentsBeforeYear));
                                                            
                                                            preg_match_all('/\d+/', $cleanIdentifier, $allNumbers);
                                                            $attempt = $allNumbers[0] ? (int) end($allNumbers[0]) : 1;
                                                            $set('code_attempt', min($attempt, 255));
                                                            
                                                            $set('code_full', $cleanIdentifier);
                                                            
                                                            // Actualizar process_type
                                                            $basicPrefix = \Illuminate\Support\Str::of($codeInfo['code_short_type'])->before(' ')->upper();
                                                            $processType = \App\Models\ProcessType::where('code_short_type', $basicPrefix)->first();
                                                            if ($processType) {
                                                                $set('process_type', $processType->description_short_type);
                                                            } else {
                                                                $set('process_type', 'Sin Clasificar');
                                                            }
                                                        }
                                                    } catch (\Exception $e) {
                                                        // Si hay error en regeneración, continuar sin campos derivados
                                                    }
                                                    
                                                    // Notificación de éxito
                                                    Notification::make()
                                                        ->title('Datos importados desde SEACE')
                                                        ->body("Se han cargado los datos del procedimiento: {$seaceTender->identifier}")
                                                        ->success()
                                                        ->duration(3000)
                                                        ->send();
                                                }
                                            }
                                        })
                                        ->columnSpanFull()
                                        ->placeholder('Buscar por nomenclatura...')
                                        ->visible(fn (callable $get) => $get('with_identifier')),

                                    // ========================================================================
                                    // 📋 INFORMACIÓN DEL PROCEDIMIENTO SEACE SELECCIONADO
                                    // ========================================================================
                                    /* Forms\Components\Placeholder::make('seace_info')
                                        ->label('Información del procedimiento SEACE')
                                        ->content(function (callable $get) {
                                            $seaceTenderId = $get('seace_tender_id');
                                            if ($seaceTenderId) {
                                                $seaceTender = \App\Models\SeaceTender::find($seaceTenderId);
                                                if ($seaceTender) {
                                                    return "
                                                    <div class='bg-blue-50 border border-blue-200 rounded-lg p-3'>
                                                        <div class='flex items-center space-x-2'>
                                                            <svg class='w-5 h-5 text-blue-600' fill='currentColor' viewBox='0 0 20 20'>
                                                                <path d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                                            </svg>
                                                            <span class='font-semibold text-blue-800'>Procedimiento SEACE Seleccionado</span>
                                                        </div>
                                                        <div class='mt-2 text-sm text-blue-700'>
                                                            <p><strong>Nomenclatura:</strong> {$seaceTender->identifier}</p>
                                                            <p><strong>Entidad:</strong> {$seaceTender->entity_name}</p>
                                                            <p><strong>Objeto:</strong> {$seaceTender->contract_object}</p>
                                                            <p><strong>Valor:</strong> {$seaceTender->currency_name} {$seaceTender->estimated_referenced_value}</p>
                                                        </div>
                                                    </div>
                                                    ";
                                                }
                                            }
                                            return "<div class='text-gray-500 text-sm'>No se ha seleccionado ningún procedimiento de SEACE</div>";
                                        })
                                        ->columnSpanFull()
                                        ->visible(fn (callable $get) => $get('seace_tender_id') !== null), */

                                    // Campo hidden para almacenar valor temporal cuando no hay nomenclatura
                                    Forms\Components\Hidden::make('identifier')
                                        ->default(fn () => 'TEMP-GENERATED-' . now()->timestamp)
                                        ->visible(fn (callable $get) => !$get('with_identifier')),

                                    TextInput::make('identifier')
                                        ->label('Nomenclatura')
                                        ->required(fn (callable $get) => $get('with_identifier'))
                                        ->maxLength(255)
                                        ->columnSpan(7)
                                        // ->readOnly(fn (callable $get) => !$get('with_identifier'))
                                        ->readOnly()
                                        ->helperText(fn (callable $get) => 
                                            $get('with_identifier') 
                                                ? 'Se llenará automáticamente al seleccionar de SEACE'
                                                : 'Se generará automáticamente al guardar'
                                        )
                                        ->visible(fn (callable $get) => $get('with_identifier'))
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Validar nomenclatura duplicada solo si no viene de SEACE
                                            if (!$get('seace_tender_id')) {
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
