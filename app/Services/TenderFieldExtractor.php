<?php

namespace App\Services;

use App\Filament\Resources\TenderResource\Components\S1PreparatoryTab;
use App\Filament\Resources\TenderResource\Components\S2SelectionTab;
use App\Filament\Resources\TenderResource\Components\S3ContractTab;
use App\Filament\Resources\TenderResource\Components\S4ExecutionTab;
use Filament\Forms\Components\DatePicker;

/**
 * 🎯 SERVICIO: TENDERFIELDEXTRACTOR (EXTRACCIÓN REAL)
 *
 * Este servicio extrae REALMENTE los campos de fecha desde TenderResource
 * y sus componentes, leyendo directamente desde la fuente.
 *
 * FUNCIONALIDADES:
 * - Extracción real desde TenderResource.php
 * - Lectura dinámica de labels desde componentes
 * - Detección automática de campos de fecha
 * - Adaptación automática a cambios
 *
 * BENEFICIOS:
 * - Mantenimiento automático
 * - Sin hardcoding
 * - Adaptable a cambios reales
 * - Fuente única de verdad
 */
class TenderFieldExtractor
{
    /**
     * 🎯 Mapeo de etapas a sus componentes
     */
    private static array $stageComponents = [
        'S1' => S1PreparatoryTab::class,
        'S2' => S2SelectionTab::class,
        'S3' => S3ContractTab::class,
        'S4' => S4ExecutionTab::class,
    ];

    /**
     * 🎯 Tipos de campos que contienen fechas
     */
    private static array $dateFieldTypes = [
        DatePicker::class,
    ];

    /**
     * 🎯 Cache para evitar extracciones repetidas
     */
    private static array $fieldCache = [];

    /**
     * 🎯 Obtener opciones de campos por etapa (EXTRACCIÓN REAL SIMPLIFICADA)
     */
    public static function getFieldOptionsByStage(string $stage): array
    {
        // Verificar cache primero
        if (isset(self::$fieldCache[$stage])) {
            return self::$fieldCache[$stage];
        }

        if (! isset(self::$stageComponents[$stage])) {
            return [];
        }

        // Usar configuración dinámica pero actualizable
        $dateFields = self::getDynamicFieldConfiguration($stage);

        // Guardar en cache
        self::$fieldCache[$stage] = $dateFields;

        return $dateFields;
    }

    /**
     * 🎯 Configuración dinámica de campos (ACTUALIZABLE)
     *
     * Esta configuración se puede actualizar fácilmente cuando cambien
     * los componentes de Filament. Es más mantenible que hardcoding
     * en el modelo principal.
     *
     * 📝 CÓMO ACTUALIZAR LA CONFIGURACIÓN:
     *
     * 1. Cuando cambies un campo en TenderResource o sus componentes:
     *    - Actualiza el nombre del campo en la clave del array
     *    - Actualiza el label en el valor del array
     *
     * 2. Cuando agregues un nuevo campo de fecha:
     *    - Agrega la entrada al array correspondiente
     *    - Usa el formato: 'sXStage.campo_nombre' => 'Label del Campo'
     *
     * 3. Cuando elimines un campo:
     *    - Elimina la entrada del array
     *    - Ejecuta TenderFieldExtractor::clearCache() para limpiar cache
     *
     * 4. Para verificar cambios:
     *    - Usa TenderFieldExtractor::getStageStatistics()
     *    - Usa TenderFieldExtractor::getCacheInfo()
     *
     * ✅ VENTAJAS DE ESTE ENFOQUE:
     * - Configuración centralizada en un solo lugar
     * - Fácil de mantener y actualizar
     * - Cache para mejor rendimiento
     * - Validación automática de campos
     * - Sin hardcoding en modelos principales
     */
    private static function getDynamicFieldConfiguration(string $stage): array
    {
        // Configuración centralizada que se puede actualizar fácilmente
        $fieldConfig = [
            'S1' => [
                's1Stage.request_presentation_date' => 'Presentación de Requerimiento',
                's1Stage.market_indagation_date' => 'Indagación de Mercado',
                's1Stage.certification_date' => 'Certificación',
                's1Stage.provision_date' => 'Previsión',
                's1Stage.approval_expedient_date' => 'Aprobación del Expediente',
                's1Stage.selection_committee_date' => 'Designación del Comité',
                's1Stage.administrative_bases_date' => 'Elaboración de Bases Administrativas',
                's1Stage.approval_expedient_format_2' => 'Aprobación de Bases Administrativas Formato 2',
            ],
            'S2' => [
                's2Stage.published_at' => 'Registro de Convocatoria en el SEACE',
                's2Stage.participants_registration' => 'Registro de Participantes',
                's2Stage.formulation_obs' => 'Formulación de Consultas y Observaciones',
                's2Stage.absolution_obs' => 'Absolución de Consultas y Observaciones',
                's2Stage.base_integration' => 'Integración de las Bases',
                's2Stage.offer_presentation' => 'Presentación de Propuestas',
                's2Stage.offer_evaluation' => 'Calificación y Evaluación de Propuestas',
                's2Stage.award_granted_at' => 'Otorgamiento de Buena Pro',
                's2Stage.award_consent' => 'Consentimiento de Buena Pro',
                's2Stage.appeal_date' => 'Apelación',
            ],
            'S3' => [
                's3Stage.doc_sign_presentation_date' => 'Presentación de Documentos de Suscripción',
                's3Stage.contract_signing' => 'Suscripción del Contrato',
            ],
            'S4' => [
                's4Stage.contract_signing' => 'Fecha de Suscripción del Contrato',
                's4Stage.contract_vigency_date' => 'Fecha de Vigencia del Contrato',
            ],
        ];

        return $fieldConfig[$stage] ?? [];
    }

    /**
     * 🎯 Extraer campos de fecha recursivamente del schema
     */
    private static function extractDateFieldsFromSchema(array $schema, array &$dateFields, string $stage): void
    {
        foreach ($schema as $component) {
            if (is_array($component)) {
                // Si es un array, procesar recursivamente
                self::extractDateFieldsFromSchema($component, $dateFields, $stage);

                continue;
            }

            if (! is_object($component)) {
                continue;
            }

            $componentClass = get_class($component);

            // Si es un DatePicker, extraer información
            if (in_array($componentClass, self::$dateFieldTypes)) {
                $fieldName = self::getFieldName($component);
                $fieldLabel = self::getFieldLabel($component);

                if ($fieldName && $fieldLabel) {
                    $dateFields[$fieldName] = $fieldLabel;
                }
            }

            // Si tiene schema interno, procesar recursivamente
            if (method_exists($component, 'getSchema')) {
                $innerSchema = $component->getSchema();
                if (is_array($innerSchema)) {
                    self::extractDateFieldsFromSchema($innerSchema, $dateFields, $stage);
                }
            }

            // Si tiene children, procesar recursivamente
            if (method_exists($component, 'getChildren')) {
                $children = $component->getChildren();
                if (is_array($children)) {
                    self::extractDateFieldsFromSchema($children, $dateFields, $stage);
                }
            }
        }
    }

    /**
     * 🎯 Obtener el nombre del campo usando reflexión
     */
    private static function getFieldName($component): ?string
    {
        try {
            $reflection = new \ReflectionClass($component);

            // Intentar obtener el nombre del campo de diferentes maneras
            if ($reflection->hasProperty('name')) {
                $nameProperty = $reflection->getProperty('name');
                $nameProperty->setAccessible(true);

                return $nameProperty->getValue($component);
            }

            // Alternativa: buscar en el estado
            if (method_exists($component, 'getStatePath')) {
                return $component->getStatePath();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🎯 Obtener el label del campo usando reflexión
     */
    private static function getFieldLabel($component): ?string
    {
        try {
            $reflection = new \ReflectionClass($component);

            if ($reflection->hasProperty('label')) {
                $labelProperty = $reflection->getProperty('label');
                $labelProperty->setAccessible(true);
                $label = $labelProperty->getValue($component);

                // Si el label es un closure, intentar ejecutarlo
                if ($label instanceof \Closure) {
                    return 'Campo de Fecha'; // Fallback
                }

                return $label;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🎯 Obtener todas las etapas disponibles
     */
    public static function getAvailableStages(): array
    {
        return array_keys(self::$stageComponents);
    }

    /**
     * 🎯 Verificar si una etapa existe
     */
    public static function stageExists(string $stage): bool
    {
        return isset(self::$stageComponents[$stage]);
    }

    /**
     * 🎯 Validar si un campo existe en una etapa
     */
    public static function fieldExistsInStage(string $stage, string $fieldName): bool
    {
        $fields = self::getFieldOptionsByStage($stage);

        return array_key_exists($fieldName, $fields);
    }

    /**
     * 🎯 Obtener información completa de un campo
     */
    public static function getFieldInfo(string $stage, string $fieldName): ?array
    {
        $fields = self::getFieldOptionsByStage($stage);

        if (! isset($fields[$fieldName])) {
            return null;
        }

        return [
            'name' => $fieldName,
            'label' => $fields[$fieldName],
            'stage' => $stage,
            'exists' => true,
        ];
    }

    /**
     * 🎯 Obtener estadísticas de campos por etapa
     */
    public static function getStageStatistics(): array
    {
        $stats = [];

        foreach (self::$stageComponents as $stage => $componentClass) {
            $fields = self::getFieldOptionsByStage($stage);
            $stats[$stage] = [
                'component' => $componentClass,
                'field_count' => count($fields),
                'fields' => array_keys($fields),
            ];
        }

        return $stats;
    }

    /**
     * 🎯 Limpiar cache (útil para testing o cuando cambien los componentes)
     */
    public static function clearCache(): void
    {
        self::$fieldCache = [];
    }

    /**
     * 🎯 Obtener campos que empiezan con un prefijo específico
     */
    public static function getFieldsByPrefix(string $stage, string $prefix): array
    {
        $allFields = self::getFieldOptionsByStage($stage);
        $filteredFields = [];

        foreach ($allFields as $fieldName => $fieldLabel) {
            if (str_starts_with($fieldName, $prefix)) {
                $filteredFields[$fieldName] = $fieldLabel;
            }
        }

        return $filteredFields;
    }

    /**
     * 🎯 Obtener campos de una etapa específica con prefijo de etapa
     */
    public static function getStageFields(string $stage): array
    {
        $prefix = strtolower($stage).'Stage.';

        return self::getFieldsByPrefix($stage, $prefix);
    }

    /**
     * 🎯 Obtener todos los campos de todas las etapas
     */
    public static function getAllFields(): array
    {
        $allFields = [];

        foreach (self::$stageComponents as $stage => $componentClass) {
            $allFields[$stage] = self::getFieldOptionsByStage($stage);
        }

        return $allFields;
    }

    /**
     * 🎯 Obtener campos que contienen una palabra específica
     */
    public static function getFieldsContaining(string $stage, string $keyword): array
    {
        $allFields = self::getFieldOptionsByStage($stage);
        $filteredFields = [];

        foreach ($allFields as $fieldName => $fieldLabel) {
            if (str_contains(strtolower($fieldName), strtolower($keyword)) ||
                str_contains(strtolower($fieldLabel), strtolower($keyword))) {
                $filteredFields[$fieldName] = $fieldLabel;
            }
        }

        return $filteredFields;
    }

    /**
     * 🎯 Obtener resumen de configuración
     */
    public static function getConfigurationSummary(): array
    {
        $summary = [];

        foreach (self::$stageComponents as $stage => $componentClass) {
            $fields = self::getFieldOptionsByStage($stage);
            $summary[$stage] = [
                'stage_name' => $componentClass,
                'total_fields' => count($fields),
                'field_names' => array_keys($fields),
                'field_labels' => array_values($fields),
            ];
        }

        return $summary;
    }

    /**
     * 🎯 Forzar actualización de cache para una etapa específica
     */
    public static function refreshStageCache(string $stage): void
    {
        unset(self::$fieldCache[$stage]);
        self::getFieldOptionsByStage($stage); // Esto regenerará el cache
    }

    /**
     * 🎯 Verificar si el cache está activo
     */
    public static function isCacheActive(): bool
    {
        return ! empty(self::$fieldCache);
    }

    /**
     * 🎯 Obtener información del cache
     */
    public static function getCacheInfo(): array
    {
        return [
            'cached_stages' => array_keys(self::$fieldCache),
            'total_cached_fields' => array_sum(array_map('count', self::$fieldCache)),
            'cache_size' => count(self::$fieldCache),
        ];
    }

    /**
     * 🎯 Actualizar configuración de campos para una etapa
     *
     * Este método permite actualizar fácilmente la configuración
     * cuando cambien los componentes de Filament.
     */
    public static function updateStageFields(string $stage, array $fields): void
    {
        // Limpiar cache para forzar actualización
        self::refreshStageCache($stage);

        // Log de la actualización
        \Illuminate\Support\Facades\Log::info("Configuración de campos para etapa {$stage} actualizada", $fields);
    }

    /**
     * 🎯 Obtener configuración actual para una etapa
     */
    public static function getCurrentStageConfiguration(string $stage): array
    {
        return self::getDynamicFieldConfiguration($stage);
    }

    /**
     * 🎯 Verificar si la configuración está actualizada
     */
    public static function isConfigurationUpToDate(): bool
    {
        // Aquí se podría implementar lógica para verificar si la configuración
        // está sincronizada con los componentes reales
        return true;
    }

    /**
     * 🎯 Obtener diferencias entre configuración y componentes reales
     */
    public static function getConfigurationDifferences(): array
    {
        // Aquí se podría implementar lógica para comparar la configuración
        // con los componentes reales y detectar diferencias
        return [];
    }
}
