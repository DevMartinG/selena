<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🎯 SERVICIO PARA API DE REQUERIMIENTOS
 *
 * Este servicio maneja la comunicación con la API de requerimientos
 * del sistema SILUCIA del Gobierno Regional de Puno.
 *
 * Características:
 * - Búsqueda por número y año
 * - Manejo de errores
 * - Logging de respuestas
 * - Formato de datos consistente
 */
class RequirementApiService
{
    /**
     * URL base de la API
     */
    private const API_BASE_URL = 'https://sistemas.regionpuno.gob.pe/siluciav2-api/api/req_segmentacion';

    /**
     * 🔍 Buscar requerimiento por número y año
     *
     * @param  string  $numero  Número del requerimiento
     * @param  string  $anio  Año del requerimiento
     * @return array|null Datos del requerimiento o null si no se encuentra
     */
    public static function searchRequirement(string $numero, string $anio): ?array
    {
        // Limpiar y formatear el número (agregar ceros a la izquierda si es necesario)
        $numeroFormateado = str_pad($numero, 4, '0', STR_PAD_LEFT);

        // 🆕 PRIMERO: Intentar API con timeout corto
        try {
            $response = Http::timeout(5)->get(self::API_BASE_URL, [
                'numero' => $numeroFormateado,
                'anio' => $anio,
            ]);

            // Si la API responde exitosamente, usar esos datos
            if ($response->successful()) {
                $data = $response->json();

                if (! empty($data) && is_array($data)) {
                    $requirement = $data[0] ?? null;

                    if ($requirement) {
                        Log::info('Requerimiento encontrado en API', [
                            'numero' => $numeroFormateado,
                            'anio' => $anio,
                        ]);

                        return $requirement;
                    }
                }
            }
        } catch (\Exception $e) {
            // La API falló, continuar con fallback JSON
            Log::warning('API no disponible, usando fallback JSON', [
                'numero' => $numeroFormateado,
                'anio' => $anio,
                'error' => $e->getMessage(),
            ]);
        }

        // 🆕 FALLBACK: Buscar en JSON cache
        return self::searchRequirementFromJson($numeroFormateado, $anio);
    }

    /**
     * 🔍 Buscar requerimiento en JSON cache (fallback cuando API no está disponible)
     *
     * @param  string  $numero  Número del requerimiento (formateado)
     * @param  string  $anio  Año del requerimiento
     * @return array|null Datos del requerimiento o null si no se encuentra
     */
    private static function searchRequirementFromJson(string $numero, string $anio): ?array
    {
        try {
            $jsonPath = storage_path('app/requirements.json');

            // Verificar si existe el archivo JSON
            if (! File::exists($jsonPath)) {
                Log::debug('Archivo requirements.json no encontrado');

                return null;
            }

            // Cargar y decodificar JSON
            $jsonContent = File::get($jsonPath);
            $data = json_decode($jsonContent, true);

            if (! is_array($data) || empty($data)) {
                return null;
            }

            // Buscar por año y número
            // Estructura: { "2025": { "4618": {...}, ... }, "2024": {...} }
            if (isset($data[$anio]) && is_array($data[$anio])) {
                // Buscar el número directamente
                if (isset($data[$anio][$numero])) {
                    Log::info('Requerimiento encontrado en JSON cache', [
                        'numero' => $numero,
                        'anio' => $anio,
                    ]);

                    return $data[$anio][$numero];
                }

                // Buscar sin ceros a la izquierda (por si el Excel tiene diferente formato)
                $numeroSinCeros = ltrim($numero, '0') ?: '0';
                if (isset($data[$anio][$numeroSinCeros])) {
                    Log::info('Requerimiento encontrado en JSON cache', [
                        'numero' => $numero,
                        'anio' => $anio,
                    ]);

                    return $data[$anio][$numeroSinCeros];
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error al buscar en JSON cache', [
                'numero' => $numero,
                'anio' => $anio,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 📋 Formatear datos del requerimiento para mostrar
     *
     * @param  array  $requirement  Datos del requerimiento
     * @return array Datos formateados
     */
    public static function formatRequirementData(array $requirement): array
    {
        return [
            'idreq' => $requirement['idreq'] ?? 'N/A',
            'numero' => $requirement['numero'] ?? 'N/A',
            'anio' => $requirement['anio'] ?? 'N/A',
            'idprocedim' => $requirement['idprocedim'] ?? 'N/A',
            'desprocedim' => $requirement['desprocedim'] ?? 'N/A',
            'idtipoadq' => $requirement['idtipoadq'] ?? 'N/A',
            'sintesis' => $requirement['sintesis'] ?? 'N/A',
            'count_items' => $requirement['count_items'] ?? 0,
            'tipo_segmentacion' => $requirement['tipo_segmentacion'] ?? 'N/A',
            'descripcion_segmentacion' => $requirement['descripcion_segmentacion'] ?? 'N/A',
            'idmeta' => $requirement['idmeta'] ?? 'N/A',
            'codmeta' => $requirement['codmeta'] ?? 'N/A',
            'desmeta' => $requirement['desmeta'] ?? 'N/A',
            'prod_proy' => $requirement['prod_proy'] ?? 'N/A',
            'iduoper' => $requirement['iduoper'] ?? 'N/A',
            'desuoper' => $requirement['desuoper'] ?? 'N/A',
            'idpersonal' => $requirement['idpersonal'] ?? 'N/A',
            'solicitante' => $requirement['solicitante'] ?? 'N/A',
            'dni_solicitante' => $requirement['dni_solicitante'] ?? 'N/A',
        ];
    }

    /**
     * 🔍 Validar parámetros de búsqueda
     *
     * @param  string  $numero  Número del requerimiento
     * @param  string  $anio  Año del requerimiento
     * @return array Errores de validación
     */
    public static function validateSearchParams(string $numero, string $anio): array
    {
        $errors = [];

        // Validar número
        if (empty($numero)) {
            $errors[] = 'El número del requerimiento es obligatorio';
        } elseif (! is_numeric($numero)) {
            $errors[] = 'El número del requerimiento debe ser numérico';
        } elseif (strlen($numero) > 10) {
            $errors[] = 'El número del requerimiento no puede tener más de 10 dígitos';
        }

        // Validar año
        if (empty($anio)) {
            $errors[] = 'El año es obligatorio';
        } elseif (! is_numeric($anio)) {
            $errors[] = 'El año debe ser numérico';
        } elseif (strlen($anio) !== 4) {
            $errors[] = 'El año debe tener 4 dígitos';
        } elseif ($anio < 2020 || $anio > 2030) {
            $errors[] = 'El año debe estar entre 2020 y 2030';
        }

        return $errors;
    }

    /**
     * 📊 Obtener información de la API
     *
     * @return array Información de la API
     */
    public static function getApiInfo(): array
    {
        return [
            'base_url' => self::API_BASE_URL,
            'timeout' => 30,
            'description' => 'API de requerimientos del sistema SILUCIA',
            'organization' => 'Gobierno Regional de Puno',
        ];
    }
}
