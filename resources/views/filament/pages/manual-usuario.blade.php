<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center space-x-3">
                <x-heroicon-m-book-open class="h-6 w-6 text-primary-600" />
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                        Manual del Usuario
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Guía completa para el uso del sistema de gestión de procesos de selección
                    </p>
                </div>
            </div>
        </div>

        @php
            $latestManual = $this->getLatestManual();
        @endphp

        <!-- Manual PDF Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-center">
                <x-heroicon-m-document-text class="h-8 w-8 text-primary-600 mx-auto mb-3" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Manual en PDF
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4 text-sm">
                    Descarga el manual completo con todas las instrucciones
                </p>
                
                @if($latestManual)
                    <div class="flex justify-center">
                        <x-filament::button
                            tag="a"
                            href="{{ $latestManual->pdf_url }}"
                            target="_blank"
                            size="lg"
                            color="primary"
                            icon="heroicon-m-arrow-down-tray"
                        >
                            📄 Abrir Manual PDF
                        </x-filament::button>
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            No hay manual disponible. Contacta al administrador.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Videos Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-center">
                <x-heroicon-m-video-camera class="h-8 w-8 text-primary-600 mx-auto mb-3" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Videos Tutoriales
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4 text-sm">
                    Accede a los videos explicativos paso a paso
                </p>
                
                @if($latestManual && $latestManual->link_videos)
                    <div class="flex justify-center">
                        <x-filament::button
                            tag="a"
                            href="{{ $latestManual->link_videos }}"
                            target="_blank"
                            size="lg"
                            color="success"
                            icon="heroicon-m-play"
                        >
                            🎥 Ver Videos Tutoriales
                        </x-filament::button>
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            No hay videos disponibles. Contacta al administrador.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Admin Section - Solo para SuperAdmin -->
        @if($this->isSuperAdmin())
            <div class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900 dark:to-primary-800 rounded-lg border border-primary-200 dark:border-primary-700 p-4">
                <div class="text-center mb-4">
                    <x-heroicon-m-cog-6-tooth class="h-6 w-6 text-primary-600 mx-auto mb-2" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Gestión del Manual
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        Actualiza el manual PDF y el link de videos
                    </p>
                </div>
                
                <form wire:submit="save">
                    {{ $this->form }}
                    
                    <div class="flex justify-center mt-4">
                        <x-filament::button
                            type="submit"
                            size="lg"
                            color="primary"
                            icon="heroicon-m-cloud-arrow-up"
                        >
                            Actualizar Manual
                        </x-filament::button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Info Section - Solo para SuperAdmin -->
        @if($this->isSuperAdmin())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 text-center">
                    Información del Manual
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-center">
                    <div>
                        <x-heroicon-m-document-text class="h-5 w-5 text-primary-600 mx-auto mb-2" />
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Formato</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">PDF</p>
                    </div>
                    <div>
                        <x-heroicon-m-clock class="h-5 w-5 text-primary-600 mx-auto mb-2" />
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Última Actualización</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $latestManual ? $latestManual->created_at->format('d/m/Y H:i') : 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
