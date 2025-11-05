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

        // ✅ Intentar parsear con formato específico DD/MM/YYYY primero (formato más común en Perú)
        // También intentar otros formatos comunes del Excel
        $formats = [
            'd/m/Y',           // 30/09/2025, 01/10/2025
            'd/m/Y H:i',       // 30/09/2025 22:54
            'd/m/Y H:i:s',     // 30/09/2025 22:54:30
            'Y-m-d',           // 2025-09-30 (ISO)
            'Y-m-d H:i:s',     // 2025-09-30 22:54:30
            'Y-m-d H:i',       // 2025-09-30 22:54
        ];

        foreach ($formats as $format) {
            try {
                // createFromFormat retorna false si falla, no lanza excepción normalmente
                $date = Carbon::createFromFormat($format, $original);
                
                // Verificar que la fecha es válida y no es false
                if ($date !== false) {
                    // Verificar que no hay errores de parsing
                    $lastErrors = Carbon::getLastErrors();
                    if ($lastErrors === false || (is_array($lastErrors) && empty($lastErrors['errors'] ?? []))) {
                        return $date->toDateString();
                    }
                }
            } catch (\Throwable $e) {
                // Continuar al siguiente formato
                continue;
            }
        }

        // ✅ Si ningún formato específico funcionó, intentar parse libre (último recurso)
        try {
            return Carbon::parse($original)->toDateString();
        } catch (\Throwable $e) {
            $errors[] = [
                'row' => $rowNum,
                'type' => 'Fecha inválida',
                'detalle' => "La fecha en '{$label}' no es válida: '{$original}'. Formato esperado: DD/MM/YYYY o DD/MM/YYYY HH:MM",
                'identifier' => $identifier,
            ];

            return null;
        }
    }

    /**
     * Nueva acción de importación para SeaceTender
     * Procesa campos básicos del procedimiento + fecha y hora de publicación
     * La unicidad se valida por combinación: identifier + publish_date + publish_date_time
     */
    protected function excelImportActionV2(): Action
    {
        return Action::make('import_excel_v2')
            ->label('Importar Excel SEACE')
            ->icon('heroicon-m-cloud-arrow-up')
            ->color('info')
            ->modalHeading('Importar procedimientos SEACE (Nuevo Formato)')
            ->modalDescription('Formato con campos básicos del procedimiento SEACE. Se procesa la fecha y hora de publicación y se valida unicidad por combinación de campos.')
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
                $updated = 0;
                $updates = []; // Para reporte de actualizaciones

                try {
                    // ========================================
                    // PROCESAMIENTO POR CHUNKS (500 filas)
                    // ========================================
                    SimpleExcelReader::create($fullPath)
                        ->getRows()
                        ->chunk(500)
                        ->each(function ($rows) use (&$errors, &$inserted, &$updated, &$updates) {
                            DB::transaction(function () use ($rows, &$errors, &$inserted, &$updated, &$updates) {
                                foreach ($rows as $index => $row) {
                                    $rowNum = $index + 2; // Considerando encabezado en la fila 1
                                    $values = array_values($row);

                                    // ========================================
                                    // IGNORAR COLUMNA "N°" (columna 0)
                                    // ========================================
                                    array_shift($values);

                                    try {
                                        // ========================================
                                        // MAPEO DE COLUMNAS PARA SEACE
                                        // Columna 0: N°
                                        // Columna 1: Nombre o Sigla de la Entidad
                                        // Columna 2: Fecha y Hora de Publicacion
                                        // Columna 3: Nomenclatura
                                        // Columna 4: Reiniciado Desde
                                        // Columna 5: Objeto de Contratación
                                        // Columna 6: Descripción del Objeto
                                        // Columna 7: VR / VE / Cuantía
                                        // Columna 8: Moneda
                                        // Columna 9: Versión SEACE
                                        // Columna 10: Procedimiento del cual se reanuda
                                        // ========================================
                                        $entityName = trim((string) ($values[0] ?? ''));           // Columna 1: Nombre o Sigla de la Entidad
                                        $publishDateRaw = trim((string) ($values[1] ?? ''));       // Columna 2: Fecha y Hora de Publicacion
                                        $identifier = trim((string) ($values[2] ?? ''));            // Columna 3: Nomenclatura
                                        $contractObject = trim((string) ($values[4] ?? ''));       // Columna 5: Objeto de Contratación
                                        $objectDescription = trim((string) ($values[5] ?? ''));    // Columna 6: Descripción del Objeto
                                        $estimatedValueRaw = trim((string) ($values[6] ?? ''));   // Columna 7: VR / VE / Cuantía
                                        $currencyNameRaw = trim((string) ($values[7] ?? ''));     // Columna 8: Moneda
                                        $resumedFrom = trim((string) ($values[9] ?? ''));         // Columna 10: Procedimiento del cual se reanuda

                                        // ========================================
                                        // PROCESAMIENTO DE FECHA Y HORA DE PUBLICACIÓN
                                        // La fecha y hora siempre tienen valor según el usuario
                                        // Formato esperado: "04/09/2025 18:43" o "16/05/2025 19:58"
                                        // Extraemos fecha y hora por separado
                                        // ========================================
                                        // Limpiar espacios adicionales y caracteres invisibles
                                        $publishDateRaw = trim($publishDateRaw);
                                        
                                        // Separar fecha y hora si vienen juntas
                                        $publishDatePart = $publishDateRaw;
                                        $publishTimePart = '00:00:00'; // Hora por defecto
                                        
                                        if (str_contains($publishDateRaw, ' ')) {
                                            $parts = explode(' ', $publishDateRaw, 2);
                                            $publishDatePart = trim($parts[0]);
                                            $publishTimePart = trim($parts[1] ?? '00:00:00');
                                            
                                            // Normalizar formato de hora (puede venir como "18:43" o "18:43:30")
                                            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $publishTimePart, $timeMatches)) {
                                                $publishTimePart = sprintf('%02d:%02d:%02d', 
                                                    (int)$timeMatches[1], 
                                                    (int)$timeMatches[2], 
                                                    isset($timeMatches[3]) ? (int)$timeMatches[3] : 0
                                                );
                                            } else {
                                                $publishTimePart = '00:00:00';
                                            }
                                        }
                                        
                                        $publishDate = $this->normalizeExcelDate($publishDatePart, true, 'Fecha de Publicación', $rowNum, $identifier, $errors);
                                        
                                        // Si la fecha es inválida, continuar con el siguiente registro
                                        if ($publishDate === null) {
                                            continue;
                                        }

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
                                        // Incluye fecha y hora de publicación como obligatorias
                                        // ========================================
                                        if (! $identifier || ! $entityName || ! $contractObject || ! $objectDescription || ! $currencyName || ! $publishDate) {
                                            $errors[] = [
                                                'row' => $rowNum,
                                                'type' => 'Campos obligatorios faltantes',
                                                'identifier' => $identifier,
                                                'entity' => $entityName,
                                                'detalle' => 'Faltan campos requeridos: Nomenclatura, Entidad, Fecha de Publicación, Objeto, Descripción o Moneda',
                                            ];

                                            continue;
                                        }

                                        // ========================================
                                        // VERIFICAR SI EL REGISTRO YA EXISTE
                                        // Buscar por la clave única: identifier + publish_date + publish_date_time
                                        // ========================================
                                        $existingRecord = SeaceTender::where('identifier', $identifier)
                                            ->where('publish_date', $publishDate)
                                            ->where('publish_date_time', $publishTimePart)
                                            ->first();

                                        if ($existingRecord) {
                                            // ========================================
                                            // ACTUALIZACIÓN DE REGISTRO EXISTENTE
                                            // Comparar y actualizar solo campos que han cambiado
                                            // ========================================
                                            $changes = [];
                                            $hasChanges = false;

                                            // Campos que pueden actualizarse
                                            $fieldsToCompare = [
                                                'entity_name' => $entityName,
                                                'contract_object' => $contractObject,
                                                'object_description' => $objectDescription,
                                                'estimated_referenced_value' => $numericValue,
                                                'currency_name' => $currencyName,
                                                'resumed_from' => $resumedFrom ?: null,
                                            ];

                                            foreach ($fieldsToCompare as $field => $newValue) {
                                                $oldValue = $existingRecord->$field;
                                                
                                                // Comparar valores (manejar decimales y strings)
                                                if ($field === 'estimated_referenced_value') {
                                                    $oldValue = (float) $oldValue;
                                                    $newValue = (float) $newValue;
                                                } else {
                                                    $oldValue = (string) ($oldValue ?? '');
                                                    $newValue = (string) ($newValue ?? '');
                                                }

                                                if ($oldValue !== $newValue) {
                                                    $changes[$field] = [
                                                        'old' => $oldValue,
                                                        'new' => $newValue,
                                                    ];
                                                    $existingRecord->$field = $newValue;
                                                    $hasChanges = true;
                                                }
                                            }

                                            if ($hasChanges) {
                                                // Actualizar el registro
                                                $existingRecord->save();
                                                $updated++;
                                                
                                                // Guardar información de actualización para reporte
                                                $updates[] = [
                                                    'row' => $rowNum,
                                                    'identifier' => $identifier,
                                                    'changes' => $changes,
                                                ];
                                            }
                                            // Si no hay cambios, simplemente continuar (registro ya está actualizado)
                                        } else {
                                            // ========================================
                                            // CREACIÓN DE NUEVO REGISTRO
                                            // Campos básicos del procedimiento + campos SEACE con fecha y hora de publicación
                                            // ========================================
                                            $seaceTender = new SeaceTender([
                                                'entity_name' => $entityName,
                                                'identifier' => $identifier,
                                                'contract_object' => $contractObject,
                                                'object_description' => $objectDescription,
                                                'estimated_referenced_value' => $numericValue,
                                                'currency_name' => $currencyName,
                                                'publish_date' => $publishDate, // ✅ Fecha de publicación procesada
                                                'publish_date_time' => $publishTimePart, // ✅ Hora de publicación procesada
                                                'resumed_from' => $resumedFrom ?: null,
                                                'tender_status_id' => $this->getDefaultTenderStatusId(), // Estado por defecto dinámico
                                                // process_type se mapea automáticamente desde code_short_type
                                            ]);

                                            // ========================================
                                            // GUARDADO CON VALIDACIÓN DE UNICIDAD COMPUESTA
                                            // El modelo maneja automáticamente la normalización del identifier
                                            // La unicidad se valida por: identifier + publish_date + publish_date_time
                                            // ========================================
                                            $seaceTender->save(); // Triggea el evento creating
                                            $inserted++;
                                        }
                                    } catch (\Throwable $e) {
                                        $message = $e->getMessage();

                                        // ========================================
                                        // MANEJO ESPECÍFICO DE ERRORES DE UNICIDAD COMPUESTA
                                        // ========================================
                                        if (str_contains($message, 'Duplicate entry') && str_contains($message, 'seace_tenders_unique_composite')) {
                                            $normalized = SeaceTender::normalizeIdentifier($values[2] ?? '');
                                            $message = "Registro duplicado: Ya existe un procedimiento SEACE con la misma combinación de Nomenclatura, Fecha de Publicación y Hora de Publicación.";
                                        }

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
                    // GUARDAR REPORTES EN SESIÓN
                    // ========================================
                    if ($errors) {
                        session()->put('seace_tenders_import_errors', $errors);
                    }
                    if ($updates) {
                        session()->put('seace_tenders_import_updates', $updates);
                    }

                    // ========================================
                    // NOTIFICACIONES DE RESULTADO
                    // ========================================
                    $totalProcessed = $inserted + $updated;
                    $hasErrors = !empty($errors);
                    $hasUpdates = !empty($updates);

                    if ($hasErrors) {
                        $body = "
                            Algunos registros fallaron.<br>
                            ➕ Insertados: <strong>{$inserted}</strong><br>";
                        
                        if ($hasUpdates) {
                            $body .= "🔄 Actualizados: <strong>{$updated}</strong><br>";
                        }
                        
                        $body .= "❌ Errores: <strong>".count($errors).'</strong><br>
                            📄 Puedes descargar el reporte para revisar los errores.';

                        Notification::make()
                            ->title('⚠️ Importación parcial')
                            ->body($body)
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
                        $body = "✅ Importación completada exitosamente.<br>";
                        $body .= "➕ Insertados: <strong>{$inserted}</strong>";
                        
                        if ($hasUpdates) {
                            $body .= "<br>🔄 Actualizados: <strong>{$updated}</strong>";
                        }

                        Notification::make()
                            ->title('✅ Importación exitosa')
                            ->body($body)
                            ->success()
                            ->persistent($hasUpdates) // Mostrar persistentemente si hay actualizaciones
                            ->actions($hasUpdates ? [
                                \Filament\Notifications\Actions\Action::make('view_updates')
                                    ->label('📋 Ver actualizaciones')
                                    ->button()
                                    ->url(route('seace-tenders.download-updates'))
                                    ->color('info')
                                    ->icon('heroicon-o-document-text'),
                            ] : [])
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