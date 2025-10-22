<?php

namespace App\Helpers;

class TenderStageColors
{
    /**
     * Obtiene la configuración de colores para una etapa específica
     */
    public static function getStageConfig(string $stageName): array
    {
        $mapping = config('tender_colors.stage_mapping');
        $stageKey = $mapping[$stageName] ?? 'no_iniciado';
        
        return config("tender_colors.stages.{$stageKey}", [
            'name' => $stageName,
            'color' => 'gray',
            'hex' => '#6B7280',
            'description' => 'Etapa desconocida'
        ]);
    }

    /**
     * Obtiene el color de Filament para una etapa
     */
    public static function getFilamentColor(string $stageName): string
    {
        $config = self::getStageConfig($stageName);
        return $config['color'];
    }

    /**
     * Obtiene el color hexadecimal para una etapa
     */
    public static function getHexColor(string $stageName): string
    {
        $config = self::getStageConfig($stageName);
        return $config['hex'];
    }

    /**
     * Obtiene el nombre completo de la etapa
     */
    public static function getStageName(string $stageName): string
    {
        $config = self::getStageConfig($stageName);
        return $config['name'];
    }

    /**
     * Obtiene la descripción de la etapa
     */
    public static function getStageDescription(string $stageName): string
    {
        $config = self::getStageConfig($stageName);
        return $config['description'];
    }

    /**
     * Obtiene todas las configuraciones de etapas
     */
    public static function getAllStages(): array
    {
        return config('tender_colors.stages', []);
    }

    /**
     * Obtiene el mapeo de etapas
     */
    public static function getStageMapping(): array
    {
        return config('tender_colors.stage_mapping', []);
    }
}
