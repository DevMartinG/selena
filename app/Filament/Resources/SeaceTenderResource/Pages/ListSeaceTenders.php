<?php

namespace App\Filament\Resources\SeaceTenderResource\Pages;

use App\Filament\Resources\SeaceTenderResource;
use App\Models\SeaceTender;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

class ListSeaceTenders extends ListRecords
{
    protected static string $resource = SeaceTenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label('Descargar plantilla')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('seace-tenders.template'))
                ->openUrlInNewTab()
                ->visible(false), // Temporalmente oculto

            $this->excelImportActionV2()
                ->visible(fn () => \Spatie\Permission\Models\Role::whereHas('users', function ($query) {
                    $query->where('users.id', auth()->id());
                })->where('name', 'SuperAdmin')->exists()), // Solo visible para SuperAdmin

            Actions\CreateAction::make(),
        ];
    }

    /**
     * Normaliza el nombre de la moneda a código estándar
     * Convierte variaciones de "SOLES" a "PEN"
     */
    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        // Si contiene "SOLES" en cualquier parte, convertir a PEN
        if (str_contains($currency, 'SOLES')) {
            return 'PEN';
        }

        // Mantener otros valores como están (USD, EUR, etc.)
        return $currency;
    }

    /**
     * Obtiene el ID del estado por defecto ("Sin Estado")
     * Si no existe, retorna null para que el usuario pueda asignarlo manualmente
     */
    private function getDefaultTenderStatusId(): ?int
    {
        $defaultStatus = \App\Models\TenderStatus::where('code', '--')->first();

        return $defaultStatus ? $defaultStatus->id : null;
    }

    private function normalizeExcelDate(mixed $value, bool $isRequired, string $label, int $rowNum, string $identifier, array &$errors): ?string
    {
        // ✅ Si ya es DateTime, lo procesamos directamente
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        // ✅ Si está vacío (después de limpiar)
        $original = trim((string) $value);
        if ($original === '') {
            if ($isRequired) {
                $errors[] = [
                    'row' => $rowNum,
                    'type' => 'Campo obligatorio faltante',
                    'detalle' => "El campo '{$label}' es obligatorio y está vacío.",
                    'identifier' => $identifier,
                ];
            }

            return null;
        }

        try {
            return Carbon::parse($original)->toDateString();
        } catch (\Throwable $e) {
            $errors[] = [
                'row' => $rowNum,
                'type' => 'Fecha inválida',
                'detalle' => "La fecha en '{$label}' no es válida.",
                'identifier' => $identifier,
            ];

            return null;
        }
    }

    /**
     * Nueva acción de importación simplificada para SeaceTender
     * Solo procesa campos básicos del procedimiento sin validaciones de fecha
     */
    protected function excelImportActionV2(): Action
    {
        return Action::make('import_excel_v2')
            ->label('Importar Excel SEACE')
            ->icon('heroicon-m-cloud-arrow-up')
            ->color('info')
            ->modalHeading('Importar procedimientos SEACE (Nuevo Formato)')
            ->modalDescription('Formato simplificado con campos básicos del procedimiento SEACE. Se validarán campos obligatorios y duplicados.')
            ->form([
                FileUpload::make('upload')
                    ->label('Archivo Excel (.xlsx)')
                    ->required()
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->storeFiles(false),
            ])
            ->action(function (array $data) {
                /** @var UploadedFile $file */
                $file = $data['upload'];

                // ========================================
                // VALIDACIÓN INICIAL DEL ARCHIVO
                // ========================================
                if (! $file instanceof UploadedFile) {
                    Notification::make()->title('Archivo no válido')->danger()->send();

                    return;
                }

                // ========================================
                // ALMACENAMIENTO TEMPORAL DEL ARCHIVO
                // ========================================
                $filename = Str::uuid().'-'.$file->getClientOriginalName();
                $relativePath = $file->storeAs('imports', $filename, 'local');
                $fullPath = storage_path('app/'.$relativePath);

                $errors = [];
                $inserted = 0;

                try {
                    // ========================================
                    // PROCESAMIENTO POR CHUNKS (500 filas)
                    // ========================================
                    SimpleExcelReader::create($fullPath)
                        ->getRows()
                        ->chunk(500)
                        ->each(function ($rows) use (&$errors, &$inserted) {
                            DB::transaction(function () use ($rows, &$errors, &$inserted) {
                                foreach ($rows as $index => $row) {
                                    $rowNum = $index + 2; // Considerando encabezado en la fila 1
                                    $values = array_values($row);

                                    // ========================================
                                    // IGNORAR COLUMNA "N°" (columna 0)
                                    // ========================================
                                    array_shift($values);

                                    try {
                                        // ========================================
                                        // MAPEO DE COLUMNAS SIMPLIFICADO PARA SEACE
                                        // Solo campos básicos del procedimiento + campos SEACE (SIN FECHAS)
                                        // ========================================
                                        $entityName = trim((string) ($values[0] ?? ''));           // Columna 1: Nombre o Sigla de la Entidad
                                        $identifier = trim((string) ($values[2] ?? ''));            // Columna 3: Nomenclatura
                                        $contractObject = trim((string) ($values[4] ?? ''));       // Columna 5: Objeto de Contratación
                                        $objectDescription = trim((string) ($values[5] ?? ''));    // Columna 6: Descripción de Objeto
                                        $estimatedValueRaw = trim((string) ($values[6] ?? ''));   // Columna 7: VR / VE / Cuantía
                                        $currencyNameRaw = trim((string) ($values[7] ?? ''));     // Columna 8: Moneda
                                        $resumedFrom = trim((string) ($values[9] ?? ''));         // Columna 10: Procedimiento del cual se reanuda

                                        // ========================================
                                        // NORMALIZACIÓN DE MONEDA
                                        // Convertir variaciones de SOLES a PEN
                                        // ========================================
                                        $currencyName = $this->normalizeCurrency($currencyNameRaw);

                                        // ========================================
                                        // PROCESAMIENTO DEL VALOR ESTIMADO
                                        // Manejar formatos numéricos con comas y puntos
                                        // ========================================
                                        $numericValue = 0; // Valor por defecto

                                        if ($estimatedValueRaw && $estimatedValueRaw !== '---') {
                                            // Limpiar espacios y caracteres no numéricos excepto comas y puntos
                                            $cleanValue = trim($estimatedValueRaw);

                                            // Remover espacios
                                            $cleanValue = str_replace(' ', '', $cleanValue);

                                            // Manejar formato con comas como separadores de miles
                                            // Ejemplo: "1,121,683.33" -> "1121683.33"
                                            if (preg_match('/^[\d,]+\.?\d*$/', $cleanValue)) {
                                                // Remover comas (separadores de miles)
                                                $cleanValue = str_replace(',', '', $cleanValue);
                                            }

                                            // Intentar convertir a número
                                            if (is_numeric($cleanValue)) {
                                                $numericValue = (float) $cleanValue;

                                                // Si es negativo, marcar como error
                                                if ($numericValue < 0) {
                                                    $errors[] = [
                                                        'row' => $rowNum,
                                                        'type' => 'Valor negativo',
                                                        'detalle' => "El valor estimado '{$estimatedValueRaw}' es negativo. No se permiten valores negativos.",
                                                        'identifier' => $identifier,
                                                    ];

                                                    continue;
                                                }
                                            } else {
                                                // Si no es numérico, asignar 0 y continuar
                                                $numericValue = 0;
                                            }
                                        }

                                        // ========================================
                                        // VALIDACIÓN DE CAMPOS OBLIGATORIOS
                                        // Solo campos básicos, sin fechas obligatorias
                                        // ========================================
                                        if (! $identifier || ! $entityName || ! $contractObject || ! $objectDescription || ! $currencyName) {
                                            $errors[] = [
                                                'row' => $rowNum,
                                                'type' => 'Campos obligatorios faltantes',
                                                'identifier' => $identifier,
                                                'entity' => $entityName,
                                                'detalle' => 'Faltan campos requeridos: Nomenclatura, Entidad, Objeto, Descripción o Moneda',
                                            ];

                                            continue;
                                        }

                                        // ========================================
                                        // CREACIÓN DEL MODELO SEACE SIMPLIFICADO
                                        // Solo campos básicos del procedimiento + campos SEACE (SIN FECHAS)
                                        // ========================================
                                        $seaceTender = new SeaceTender([
                                            'entity_name' => $entityName,
                                            'identifier' => $identifier,
                                            'contract_object' => $contractObject,
                                            'object_description' => $objectDescription,
                                            'estimated_referenced_value' => $numericValue,
                                            'currency_name' => $currencyName,
                                            'publish_date' => null, // No procesamos fechas en esta versión simplificada
                                            'resumed_from' => $resumedFrom ?: null,
                                            'tender_status_id' => $this->getDefaultTenderStatusId(), // Estado por defecto dinámico
                                            // process_type se mapea automáticamente desde code_short_type
                                        ]);

                                        // ========================================
                                        // GUARDADO CON VALIDACIÓN DE DUPLICADOS
                                        // El modelo maneja automáticamente la normalización del identifier
                                        // ========================================
                                        $seaceTender->save(); // Triggea el evento creating para validar duplicados

                                        $inserted++;
                                    } catch (\Throwable $e) {
                                        $message = $e->getMessage();

                                        // ========================================
                                        // ❌ MANEJO ESPECÍFICO DE ERRORES DE DUPLICADO (COMENTADO - Será removido en cambio futuro)
                                        // ========================================
                                        // if (str_contains($message, 'Duplicate entry') && str_contains($message, 'code_full')) {
                                        //     $normalized = SeaceTender::normalizeIdentifier($values[2] ?? '');
                                        //     $message = "Duplicado: '{$normalized}'. Ya existe un procedimiento SEACE con esta nomenclatura en el sistema.";
                                        // }

                                        $errors[] = [
                                            'row' => $rowNum,
                                            'type' => 'Error al insertar',
                                            'detalle' => $message,
                                            'identifier' => $values[2] ?? '',
                                        ];
                                    }
                                }
                            });
                        });

                    // ========================================
                    // LIMPIEZA DEL ARCHIVO TEMPORAL
                    // ========================================
                    Storage::disk('local')->delete($relativePath);

                    // ========================================
                    // NOTIFICACIONES DE RESULTADO
                    // ========================================
                    if ($errors) {
                        session()->put('seace_tenders_import_errors', $errors);

                        Notification::make()
                            ->title('⚠️ Importación parcial')
                            ->body("
                            Algunos registros fallaron.<br>
                            ➕ Insertados: <strong>{$inserted}</strong><br>
                            ❌ Errores: <strong>".count($errors).'</strong><br>
                            📄 Puedes descargar el reporte para revisar los errores.
                        ')
                            ->warning()
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download_errors')
                                    ->label('📄 Descargar errores')
                                    ->button()
                                    ->url(route('seace-tenders.download-errors'))
                                    ->color('danger')
                                    ->icon('heroicon-o-arrow-down-on-square'),
                            ])
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Importación exitosa')
                            ->body("Se insertaron {$inserted} procedimientos SEACE exitosamente.")
                            ->success()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    // ========================================
                    // LIMPIEZA EN CASO DE ERROR GENERAL
                    // ========================================
                    Storage::disk('local')->delete($relativePath);

                    Notification::make()
                        ->title('Error al procesar archivo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function getTabs(): array
    {
        // Obtener todos los valores únicos de code_short_type
        $allTypes = SeaceTender::query()
            ->select('code_short_type')
            ->distinct()
            ->pluck('code_short_type')
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $tabs = [];

        // Tab "Todos"
        $tabs['all'] = Tab::make()
            ->label('Todos')
            ->badge(SeaceTender::count())
            ->modifyQueryUsing(fn ($query) => $query);

        // Tabs dinámicos por code_short_type
        foreach ($allTypes as $type) {
            $tabs[$type] = Tab::make()
                ->label($type)
                ->badge(SeaceTender::where('code_short_type', $type)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('code_short_type', $type));
        }

        return $tabs;
    }

    public function getTitle(): string
    {
        $activeTab = $this->activeTab;

        if (empty($activeTab) || $activeTab === 'all') {
            return 'Procedimientos SEACE - Todos';
        }

        return "Procedimientos SEACE - {$activeTab}";
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return 'full';
    }
}