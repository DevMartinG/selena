<?php

namespace App\Filament\Pages;

use App\Models\ManualUsuario;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManualUsuarioPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-m-book-open';
    
    protected static string $view = 'filament.pages.manual-usuario';
    
    protected static ?string $title = 'Manual del Usuario';
    
    protected static ?string $navigationLabel = 'Manual';
    
    protected static ?int $navigationSort = 101;

    public ?array $data = [];

    public function mount(): void
    {
        $latestManual = ManualUsuario::getLatest();
        $this->data = [
            'link_videos' => $latestManual?->link_videos ?? '',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('manual_pdf')
                    ->label('Manual PDF')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240) // 10MB
                    ->directory('manual-usuario')
                    ->required()
                    ->helperText('Solo archivos PDF. Máximo 10MB'),
                
                TextInput::make('link_videos')
                    ->label('Link de Videos Tutoriales')
                    ->url()
                    ->placeholder('https://drive.google.com/...')
                    ->required()
                    ->helperText('Link a Drive, YouTube o plataforma de videos'),
                
                TextInput::make('version')
                    ->label('Versión')
                    ->default('1.0')
                    ->required()
                    ->helperText('Ejemplo: 1.0, 1.1, 2.0, etc.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        // Validar que el usuario sea SuperAdmin
        if (!$this->isSuperAdmin()) {
            Notification::make()
                ->title('Acceso denegado')
                ->body('Solo el SuperAdmin puede actualizar el manual')
                ->danger()
                ->send();
            return;
        }

        try {
            // Crear nuevo registro de manual
            ManualUsuario::create([
                'nombre_archivo' => $data['manual_pdf'],
                'ruta_archivo' => 'manual-usuario/' . $data['manual_pdf'],
                'version' => $data['version'],
                'link_videos' => $data['link_videos'],
                'subido_por' => auth()->id(),
            ]);

            Notification::make()
                ->title('Manual actualizado')
                ->body('El manual del usuario ha sido actualizado exitosamente')
                ->success()
                ->send();

            // Limpiar el formulario
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al actualizar')
                ->body('Hubo un problema al actualizar el manual: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getLatestManual(): ?ManualUsuario
    {
        return ManualUsuario::getLatest();
    }

    public function isSuperAdmin(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasRole('SuperAdmin');
    }
}