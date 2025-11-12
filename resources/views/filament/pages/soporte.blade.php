<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center space-x-3">
                <x-heroicon-m-lifebuoy class="h-6 w-6 text-primary-600" />
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                        Soporte Técnico
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Centro de ayuda para el sistema de gestión de procesos de selección
                    </p>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                ¿Cómo usar el soporte?
            </h3>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <p>• <strong>Problemas técnicos:</strong> Errores del sistema, fallos de conexión, problemas de acceso</p>
                <p>• <strong>Consultas de uso:</strong> Dudas sobre funcionalidades, procesos de selección, configuración</p>
                <p>• <strong>Solicitudes de mejora:</strong> Sugerencias para optimizar el sistema</p>
                <p>• <strong>Capacitación:</strong> Solicitar entrenamiento sobre el uso del sistema</p>
            </div>
        </div>

        <!-- Action Button Section -->
        <div class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900 dark:to-primary-800 rounded-lg border border-primary-200 dark:border-primary-700 p-4">
            <div class="text-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    ¿Necesitas ayuda?
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4 text-sm">
                    Haz clic en el botón para abrir el formulario de soporte
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

        <!-- Contact Info Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 text-center">
                Información de Contacto
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div>
                    <x-heroicon-m-phone class="h-5 w-5 text-primary-600 mx-auto mb-2" />
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Teléfono</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">986 884 118</p>
                </div>
                <div>
                    <x-heroicon-m-user class="h-5 w-5 text-primary-600 mx-auto mb-2" />
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Encargado del Sistema</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Dennis Martin</p>
                </div>
                <div>
                    <x-heroicon-m-clock class="h-5 w-5 text-primary-600 mx-auto mb-2" />
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Horario de Atención</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Lunes a Viernes: 8:00 - 17:00</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

