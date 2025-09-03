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
                ->openUrlInNewTab(), // opcional: abre en una nueva pestaña

            $this->excelImportAction(),
            Actions\CreateAction::make(),
        ];
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
                $fullPath = Storage::disk('local')->path($relativePath);

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
                                    array_shift($values); // <- importante

                                    // ✅ Normalizar accidentalmente valores tipo fecha en columnas equivocadas
                                    // Limpiar campos específicos que deben ser fechas
                                    $fechaIndices = [
                                        1 => 'published_at',
                                        9 => 'absolution_obs',
                                        10 => 'offer_presentation',
                                        11 => 'award_granted_at',
                                        12 => 'award_consent',
                                        17 => 'contract_signing',
                                    ];

                                    foreach ($fechaIndices as $i => $campo) {
                                        if (! empty($values[$i])) {
                                            if ($values[$i] instanceof \DateTimeInterface) {
                                                $values[$i] = Carbon::instance($values[$i])->toDateString();
                                            } else {
                                                try {
                                                    $values[$i] = Carbon::parse(trim((string) $values[$i]))->toDateString();
                                                } catch (\Throwable $e) {
                                                    $values[$i] = null; // ← importante: fuerza a null si no se puede parsear
                                                    $label = $columnLabels[$campo] ?? $campo;

                                                    $errors[] = [
                                                        'row' => $rowNum,
                                                        'type' => 'Fecha inválida',
                                                        'detalle' => "No se pudo interpretar la fecha para la columna '{$label}'",
                                                        'identifier' => $values[2] ?? '',
                                                        'entity' => $values[0] ?? '',
                                                    ];

                                                    continue 2; // saltar la fila completa
                                                }
                                            }
                                        }
                                    }

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
                                            'absolution_obs' => $values[9] ?? null,
                                            'offer_presentation' => $values[10] ?? null,
                                            'award_granted_at' => $values[11] ?? null,
                                            'award_consent' => $values[12] ?? null,
                                            'current_status' => $values[13] ?? '',
                                            'awarded_tax_id' => $values[14] ?? null,
                                            'awarded_legal_name' => $values[15] ?? null,
                                            'awarded_amount' => (float) str_replace([','], '', (string) $values[16]),
                                            'contract_signing' => $values[17] ?? null,
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

                                        if (str_contains($message, 'SQLSTATE') && str_contains($message, 'Incorrect date value')) {
                                            // Detectar campo y valor
                                            preg_match("/Incorrect date value: '([^']+)' for column `[^`]+`\.`[^`]+`\.`([^`]+)`/", $message, $matches);

                                            if (isset($matches[1], $matches[2])) {
                                                $invalidValue = $matches[1];
                                                $field = $matches[2];

                                                // Convertir campo a etiqueta
                                                $label = $columnLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                                                $message = "Fecha inválida para la columna '{$label}': '{$invalidValue}'";
                                            } else {
                                                $message = 'Error al insertar: Fecha inválida.';
                                            }
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
