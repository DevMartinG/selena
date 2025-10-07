<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Models\Tender;
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

class ListTenders extends ListRecords
{
    protected static string $resource = TenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label('Descargar plantilla')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('tenders.template'))
                ->openUrlInNewTab()
                ->visible(false), // Temporalmente oculto

            $this->excelImportAction()
                ->visible(false), // Temporalmente oculto

            /* $this->excelImportActionV2()
                ->visible(fn () => \Spatie\Permission\Models\Role::whereHas('users', function ($query) {
                    $query->where('users.id', auth()->id());
                })->where('name', 'SuperAdmin')->exists()), // Solo visible para SuperAdmin */

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

    protected function excelImportAction(): Action
    {
        return Action::make('import_excel')
            ->label('Importar Excel')
            ->icon('heroicon-m-cloud-arrow-up')
            ->color('info')
            ->modalHeading('Importar procedimientos de selección')
            ->modalDescription('Asegúrate de seguir el formato de la plantilla. Se validarán fechas, montos y duplicados.')
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

                if (! $file instanceof UploadedFile) {
                    Notification::make()->title('Archivo no válido')->danger()->send();

                    return;
                }

                $filename = Str::uuid().'-'.$file->getClientOriginalName();
                $relativePath = $file->storeAs('imports', $filename, 'local');
                $fullPath = storage_path('app/'.$relativePath);

                $errors = [];
                $inserted = 0;

                try {
                    SimpleExcelReader::create($fullPath)
                        ->getRows()
                        ->chunk(500)
                        ->each(function ($rows) use (&$errors, &$inserted) {
                            DB::transaction(function () use ($rows, &$errors, &$inserted) {

                                $columnLabels = [
                                    'published_at' => 'Fecha de Publicacion',
                                    'absolution_obs' => 'Absolución de Consultas / Obs Integración de Bases',
                                    'offer_presentation' => 'Presentación de Ofertas',
                                    'award_granted_at' => 'Otorgamiento de la Buena Pro',
                                    'award_consent' => 'Consentimiento de la Buena Pro',
                                    'contract_signing' => 'Fecha de Suscripción del Contrato',
                                ];

                                foreach ($rows as $index => $row) {
                                    $rowNum = $index + 2; // considerando encabezado en la fila 1
                                    $values = array_values($row);

                                    // Ignorar la primera columna "N°" del Excel (columna 0)
                                    array_shift($values);

                                    try {
                                        // Mapeo por índice (0-based)
                                        $entityName = trim((string) ($values[0] ?? ''));
                                        $publishedAt = $values[1] ?? null;
                                        $identifier = trim((string) ($values[2] ?? ''));

                                        if (! $identifier || ! $entityName || ! $publishedAt) {
                                            $errors[] = [
                                                'row' => $rowNum,
                                                'type' => 'Campos obligatorios faltantes',
                                                'identifier' => $identifier,
                                                'entity' => $entityName,
                                                'detalle' => 'Faltan campos requeridos',
                                            ];

                                            continue;
                                        }

                                        $publishedAt = $this->normalizeExcelDate(
                                            $values[1] ?? '',
                                            true,
                                            'Fecha de Publicación',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        $absolutionObs = $this->normalizeExcelDate(
                                            $values[9] ?? '',
                                            false,
                                            'Absolución de Consultas / Obs Integración de Bases',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        $offerPresentation = $this->normalizeExcelDate(
                                            $values[10] ?? '',
                                            false,
                                            'Presentación de Ofertas',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        $awardGrantedAt = $this->normalizeExcelDate(
                                            $values[11] ?? '',
                                            false,
                                            'Otorgamiento de la Buena Pro',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        $awardConsent = $this->normalizeExcelDate(
                                            $values[12] ?? '',
                                            false,
                                            'Consentimiento de la Buena Pro',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        $contractSigning = $this->normalizeExcelDate(
                                            $values[17] ?? '',
                                            false,
                                            'Fecha de Suscripción del Contrato',
                                            $rowNum,
                                            $identifier,
                                            $errors
                                        );

                                        // Si hubo error por fecha requerida, saltar esta fila
                                        if (! $publishedAt) {
                                            return;
                                        }

                                        // Preparar instancia
                                        $tender = new Tender([
                                            'entity_name' => $entityName,
                                            'published_at' => $publishedAt,
                                            'identifier' => $identifier,
                                            'restarted_from' => $values[3] ?? null,
                                            'contract_object' => $values[4] ?? '',
                                            'object_description' => $values[5] ?? '',
                                            'cui_code' => $values[6] ?? null,
                                            'estimated_referenced_value' => (float) str_replace([','], '', (string) $values[7]),
                                            'currency_name' => trim((string) ($values[8] ?? '')),
                                            'absolution_obs' => $absolutionObs,
                                            'offer_presentation' => $offerPresentation,
                                            'award_granted_at' => $awardGrantedAt,
                                            'award_consent' => $awardConsent,
                                            'current_status' => $values[13] ?? '',
                                            'awarded_tax_id' => $values[14] ?? null,
                                            'awarded_legal_name' => $values[15] ?? null,
                                            'awarded_amount' => (float) str_replace([','], '', (string) $values[16]),
                                            'contract_signing' => $contractSigning,
                                            'adjusted_amount' => (float) str_replace([','], '', (string) $values[18]),
                                            'observation' => $values[19] ?? null,
                                            'selection_comittee' => $values[20] ?? null,
                                            'contract_execution' => $values[21] ?? null,
                                            'contract_details' => $values[22] ?? null,
                                        ]);

                                        // Validar duplicado por code_full
                                        $tender->fill([
                                            'identifier' => $identifier,
                                        ]);

                                        $tender->save(); // Triggea el evento creating

                                        $inserted++;
                                    } catch (\Throwable $e) {
                                        $message = $e->getMessage();

                                        // Error SQL por fecha inválida
                                        if (str_contains($message, 'SQLSTATE') && str_contains($message, 'Incorrect date value')) {
                                            preg_match("/Incorrect date value: '([^']+)' for column `[^`]+`\.`[^`]+`\.`([^`]+)`/", $message, $matches);

                                            if (isset($matches[1], $matches[2])) {
                                                $invalidValue = $matches[1];
                                                $field = $matches[2];
                                                $label = $columnLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                                                $message = "Fecha inválida para la columna '{$label}': '{$invalidValue}'";
                                            } else {
                                                $message = 'Error al insertar: Fecha inválida.';
                                            }
                                        }

                                        // Error por clave duplicada en 'code_full'
                                        elseif (
                                            str_contains($message, 'Duplicate entry') &&
                                            str_contains($message, 'for key') &&
                                            str_contains($message, 'code_full')
                                        ) {
                                            $normalized = \App\Models\Tender::normalizeIdentifier($values[2] ?? '');

                                            $message = "Duplicado : '{$normalized}' . Ya existe un procedimiento con esta nomenclatura en el sistema.";
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

                    Storage::disk('local')->delete($relativePath);

                    if ($errors) {
                        session()->put('tenders_import_errors', $errors);

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
                                    ->url(route('tenders.download-errors'))
                                    ->color('danger')
                                    ->icon('heroicon-o-arrow-down-on-square'),
                            ])
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Importación exitosa')
                            ->body("Se insertaron {$inserted} registros.")
                            ->success()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Storage::disk('local')->delete($relativePath);

                    Notification::make()
                        ->title('Error al procesar archivo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Nueva acción de importación simplificada para el formato actualizado
     * Solo procesa campos básicos del procedimiento sin validaciones de fecha
     */
    protected function excelImportActionV2(): Action
    {
        return Action::make('import_excel_v2')
            ->label('Importar Excel (Nuevo Formato)')
            ->icon('heroicon-m-cloud-arrow-up')
            ->color('info')
            ->modalHeading('Importar procedimientos (Nuevo Formato)')
            ->modalDescription('Formato simplificado con campos básicos del procedimiento. Se validarán campos obligatorios y duplicados.')
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
                                        // MAPEO DE COLUMNAS SIMPLIFICADO
                                        // Solo campos básicos del procedimiento
                                        // ========================================
                                        $entityName = trim((string) ($values[0] ?? ''));           // Columna 1: Nombre o Sigla de la Entidad
                                        $identifier = trim((string) ($values[2] ?? ''));            // Columna 3: Nomenclatura
                                        $contractObject = trim((string) ($values[4] ?? ''));       // Columna 5: Objeto de Contratación
                                        $objectDescription = trim((string) ($values[5] ?? ''));    // Columna 6: Descripción de Objeto
                                        $estimatedValueRaw = trim((string) ($values[6] ?? ''));   // Columna 7: VR / VE / Cuantía (corregido)
                                        $currencyNameRaw = trim((string) ($values[7] ?? ''));     // Columna 8: Moneda (corregido)

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
                                        // Solo campos básicos, sin fechas
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
                                        // CREACIÓN DEL MODELO SIMPLIFICADO
                                        // Solo campos básicos del procedimiento
                                        // ========================================
                                        $tender = new Tender([
                                            'entity_name' => $entityName,
                                            'identifier' => $identifier,
                                            'contract_object' => $contractObject,
                                            'object_description' => $objectDescription,
                                            'estimated_referenced_value' => $numericValue,
                                            'currency_name' => $currencyName,
                                            'tender_status_id' => $this->getDefaultTenderStatusId(), // Estado por defecto dinámico
                                            // process_type se mapea automáticamente desde code_short_type
                                        ]);

                                        // ========================================
                                        // GUARDADO CON VALIDACIÓN DE DUPLICADOS
                                        // El modelo maneja automáticamente la normalización del identifier
                                        // ========================================
                                        $tender->save(); // Triggea el evento creating para validar duplicados

                                        $inserted++;
                                    } catch (\Throwable $e) {
                                        $message = $e->getMessage();

                                        // ========================================
                                        // MANEJO ESPECÍFICO DE ERRORES DE DUPLICADO
                                        // ========================================
                                        if (str_contains($message, 'Duplicate entry') && str_contains($message, 'code_full')) {
                                            $normalized = \App\Models\Tender::normalizeIdentifier($values[2] ?? '');
                                            $message = "Duplicado: '{$normalized}'. Ya existe un procedimiento con esta nomenclatura en el sistema.";
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
                    // NOTIFICACIONES DE RESULTADO
                    // ========================================
                    if ($errors) {
                        session()->put('tenders_import_errors', $errors);

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
                                    ->url(route('tenders.download-errors'))
                                    ->color('danger')
                                    ->icon('heroicon-o-arrow-down-on-square'),
                            ])
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Importación exitosa')
                            ->body("Se insertaron {$inserted} procedimientos exitosamente.")
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
        $allTypes = Tender::query()
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
            ->badge(Tender::count())
            ->modifyQueryUsing(fn ($query) => $query);

        // Tabs dinámicos por code_short_type
        foreach ($allTypes as $type) {
            $tabs[$type] = Tab::make()
                ->label($type)
                ->badge(Tender::where('code_short_type', $type)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('code_short_type', $type));
        }

        return $tabs;
    }

    public function getTitle(): string
    {
        $activeTab = $this->activeTab;

        if (empty($activeTab) || $activeTab === 'all') {
            return 'Procedimientos de Selección - Todos';
        }

        return "Procedimientos de Selección - {$activeTab}";
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return 'full';
    }
}
