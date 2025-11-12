<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\SimpleExcel\SimpleExcelReader;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelImportAction()
                ->visible(fn () => \Spatie\Permission\Models\Role::whereHas('users', function ($query) {
                    $query->where('users.id', auth()->id());
                })->where('name', 'SuperAdmin')->exists()), // Solo visible para SuperAdmin

            Actions\CreateAction::make(),
        ];
    }

    /**
     * 🔐 Genera contraseña automática basada en NIN + iniciales
     * Formato: <nin><primera_letra_name><primera_letra_last_name>
     */
    private function generatePassword(string $nin, string $name, string $lastName): string
    {
        $firstLetterName = strtoupper(substr(trim($name), 0, 1));
        $firstLetterLastName = strtoupper(substr(trim($lastName), 0, 1));
        
        return $nin . $firstLetterName . $firstLetterLastName;
    }

    /**
     * 👤 Genera username automático usando el NIN
     * Formato: <nin>
     */
    private function generateUsername(string $email, string $nin): string
    {
        return $nin;
    }

    /**
     * 📊 Acción de importación de usuarios desde Excel
     */
    protected function excelImportAction(): Action
    {
        return Action::make('import_users_excel')
            ->label('Importar Usuarios Excel')
            ->icon('heroicon-m-cloud-arrow-up')
            ->color('info')
            ->modalHeading('Importar Usuarios desde Excel')
            ->modalDescription('Importa usuarios con generación automática de contraseñas y asignación del rol "Coordinador". Formato: id, name, last_name, nin, email')
            ->form([
                FileUpload::make('upload')
                    ->label('Archivo Excel/CSV (.xlsx, .csv)')
                    ->required()
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        'application/csv',
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
                $skipped = 0;

                try {
                    // ========================================
                    // PROCESAMIENTO POR CHUNKS (500 filas)
                    // ========================================
                    SimpleExcelReader::create($fullPath)
                        ->getRows()
                        ->chunk(500)
                        ->each(function ($rows) use (&$errors, &$inserted, &$updated, &$skipped) {
                            DB::transaction(function () use ($rows, &$errors, &$inserted, &$updated, &$skipped) {
                                foreach ($rows as $index => $row) {
                                    $rowNum = $index + 2; // Considerando encabezado en la fila 1
                                    $values = array_values($row);

                                    try {
                                        // ========================================
                                        // MAPEO DE COLUMNAS DEL EXCEL
                                        // Formato: id, name, last_name, nin, email
                                        // ========================================
                                        $excelId = trim((string) ($values[0] ?? ''));           // Columna 0: ID (opcional, para referencia)
                                        $name = trim((string) ($values[1] ?? ''));              // Columna 1: name
                                        $lastName = trim((string) ($values[2] ?? ''));          // Columna 2: last_name
                                        $nin = trim((string) ($values[3] ?? ''));                // Columna 3: nin
                                        $email = trim((string) ($values[4] ?? ''));             // Columna 4: email

                                        // ========================================
                                        // VALIDACIÓN DE CAMPOS OBLIGATORIOS
                                        // ========================================
                                        if (! $name || ! $lastName || ! $nin || ! $email) {
                                            $errors[] = [
                                                'row' => $rowNum,
                                                'type' => 'Campos obligatorios faltantes',
                                                'detalle' => 'Faltan campos requeridos: name, last_name, nin o email',
                                                'identifier' => $email ?: $nin,
                                            ];
                                            continue;
                                        }

                                        // ========================================
                                        // VALIDACIÓN DE EMAIL
                                        // ========================================
                                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                            $errors[] = [
                                                'row' => $rowNum,
                                                'type' => 'Email inválido',
                                                'detalle' => "El email '{$email}' no tiene un formato válido",
                                                'identifier' => $email,
                                            ];
                                            continue;
                                        }

                                        // ========================================
                                        // VERIFICAR SI EL USUARIO YA EXISTE (por NIN)
                                        // ========================================
                                        $existingUser = User::where('nin', $nin)->first();

                                        if ($existingUser) {
                                            // ========================================
                                            // USUARIO EXISTENTE - VERIFICAR SI HAY CAMBIOS
                                            // ========================================
                                            $hasChanges = false;
                                            $changes = [];

                                            if ($existingUser->name !== $name) {
                                                $hasChanges = true;
                                                $changes[] = "name: '{$existingUser->name}' → '{$name}'";
                                                $existingUser->name = $name;
                                            }

                                            if ($existingUser->last_name !== $lastName) {
                                                $hasChanges = true;
                                                $changes[] = "last_name: '{$existingUser->last_name}' → '{$lastName}'";
                                                $existingUser->last_name = $lastName;
                                            }

                                            if ($existingUser->email !== $email) {
                                                $hasChanges = true;
                                                $changes[] = "email: '{$existingUser->email}' → '{$email}'";
                                                $existingUser->email = $email;
                                            }

                                            // Actualizar username si es necesario
                                            $newUsername = $this->generateUsername($email, $nin);
                                            if ($existingUser->username !== $newUsername) {
                                                $hasChanges = true;
                                                $changes[] = "username: '{$existingUser->username}' → '{$newUsername}'";
                                                $existingUser->username = $newUsername;
                                            }

                                            if ($hasChanges) {
                                                $existingUser->save();
                                                $updated++;
                                            } else {
                                                $skipped++;
                                            }
                                        } else {
                                            // ========================================
                                            // USUARIO NUEVO - CREAR
                                            // ========================================
                                            // Generar contraseña y username automáticos
                                            $password = $this->generatePassword($nin, $name, $lastName);
                                            $username = $this->generateUsername($email, $nin);

                                            $user = User::create([
                                                'name' => $name,
                                                'last_name' => $lastName,
                                                'nin' => $nin,
                                                'email' => $email,
                                                'username' => $username,
                                                'password' => Hash::make($password),
                                            ]);

                                            // ========================================
                                            // ASIGNAR ROL "COORDINADOR"
                                            // ========================================
                                            $coordinadorRole = Role::where('name', 'Coordinador')->first();
                                            if ($coordinadorRole) {
                                                $user->assignRole($coordinadorRole);
                                            }

                                            $inserted++;
                                        }

                                    } catch (\Throwable $e) {
                                        $message = $e->getMessage();

                                        // ========================================
                                        // MANEJO ESPECÍFICO DE ERRORES DE DUPLICADO
                                        // ========================================
                                        if (str_contains($message, 'Duplicate entry') && str_contains($message, 'email')) {
                                            $message = "Email duplicado: '{$email}'. Ya existe un usuario con este email en el sistema.";
                                        } elseif (str_contains($message, 'Duplicate entry') && str_contains($message, 'nin')) {
                                            $message = "NIN duplicado: '{$nin}'. Ya existe un usuario con este NIN en el sistema.";
                                        }

                                        $errors[] = [
                                            'row' => $rowNum,
                                            'type' => 'Error al procesar',
                                            'detalle' => $message,
                                            'identifier' => $email ?: $nin,
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
                        session()->put('users_import_errors', $errors);

                        Notification::make()
                            ->title('⚠️ Importación con errores')
                            ->body("
                                ➕ Nuevos usuarios: <strong>{$inserted}</strong><br>
                                🔄 Actualizados: <strong>{$updated}</strong><br>
                                ⏭️ Sin cambios: <strong>{$skipped}</strong><br>
                                ❌ Errores: <strong>".count($errors).'</strong><br>
                                📄 Puedes descargar el reporte para revisar los errores.
                            ')
                            ->warning()
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download_errors')
                                    ->label('📄 Descargar errores')
                                    ->button()
                                    ->url(route('users.download-errors'))
                                    ->color('danger')
                                    ->icon('heroicon-o-arrow-down-on-square'),
                            ])
                            ->send();
                    } else {
                        Notification::make()
                            ->title('✅ Importación exitosa')
                            ->body("
                                ➕ Nuevos usuarios: <strong>{$inserted}</strong><br>
                                🔄 Actualizados: <strong>{$updated}</strong><br>
                                ⏭️ Sin cambios: <strong>{$skipped}</strong>
                            ")
                            ->success()
                            ->send();
                    }

                } catch (\Throwable $e) {
                    // ========================================
                    // LIMPIEZA EN CASO DE ERROR GENERAL
                    // ========================================
                    Storage::disk('local')->delete($relativePath);

                    Notification::make()
                        ->title('❌ Error al procesar archivo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
