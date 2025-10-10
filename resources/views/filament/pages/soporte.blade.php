<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <x-heroicon-m-lifebuoy class="h-8 w-8 text-primary-600" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Soporte Técnico
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        Centro de ayuda y soporte para el sistema de gestión de procesos de selección
                    </p>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                ¿Cómo solicitar soporte?
            </h2>
            
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                            <span class="text-primary-600 dark:text-primary-400 font-semibold text-sm">1</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Haz clic en el botón de abajo</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">
                            Presiona "Abrir Formulario de Soporte" para acceder al sistema de tickets
                        </p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                            <span class="text-primary-600 dark:text-primary-400 font-semibold text-sm">2</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Completa el formulario</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">
                            Describe tu problema o consulta de manera detallada para brindarte la mejor ayuda
                        </p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                            <span class="text-primary-600 dark:text-primary-400 font-semibold text-sm">3</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Recibe seguimiento</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">
                            Nuestro equipo técnico revisará tu solicitud y te contactará para resolver tu consulta
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Button Section -->
        <div class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900 dark:to-primary-800 rounded-lg border border-primary-200 dark:border-primary-700 p-6">
            <div class="text-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    ¿Necesitas ayuda?
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Haz clic en el botón de abajo para abrir el formulario de soporte en una nueva pestaña
                </p>
                
                <div class="flex justify-center">
                    <x-filament::button
                        tag="a"
                        href="https://sistemas.regionpuno.gob.pe/incidencias/ticketcreate"
                        target="_blank"
                        size="lg"
                        color="primary"
                        icon="heroicon-m-ticket"
                    >
                        Abrir Formulario de Soporte
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- Additional Help Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                Información adicional
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start space-x-3">
                    <x-heroicon-m-clock class="h-5 w-5 text-gray-400 mt-1" />
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">Tiempo de respuesta</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Respuesta promedio: 24-48 horas
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-3">
                    <x-heroicon-m-envelope class="h-5 w-5 text-gray-400 mt-1" />
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">Contacto directo</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Para emergencias: soporte@regionpuno.gob.pe
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-3">
                    <x-heroicon-m-document-text class="h-5 w-5 text-gray-400 mt-1" />
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">Documentación</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Manual de usuario disponible en el sistema
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-3">
                    <x-heroicon-m-shield-check class="h-5 w-5 text-gray-400 mt-1" />
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">Seguridad</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Todos los tickets son confidenciales
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
