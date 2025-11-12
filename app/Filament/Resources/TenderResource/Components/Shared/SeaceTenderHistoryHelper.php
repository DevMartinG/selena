<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\SeaceTender;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;

/**
 * Componente reutilizable para mostrar el historial de SeaceTender
 * 
 * Este componente puede ser usado en:
 * - Actions de formularios (GeneralInfoTab)
 * - Actions de tablas (TenderResource)
 * - Cualquier lugar donde se necesite mostrar el historial
 */
class SeaceTenderHistoryHelper
{
    /**
     * Renderizar encabezado del historial
     * 
     * @param  string  $baseCode
     * @param  SeaceTender|null  $currentSeaceTender
     * @return HtmlString
     */
    public static function renderHistoryHeader(string $baseCode, ?SeaceTender $currentSeaceTender = null): HtmlString
    {
        if (!$currentSeaceTender) {
            return new HtmlString(
                '<div class="space-y-2">
                    <h3 class="text-lg font-bold">' . htmlspecialchars($baseCode, ENT_QUOTES, 'UTF-8') . '</h3>
                </div>'
            );
        }
        
        $objectDescription = htmlspecialchars($currentSeaceTender->object_description ?? 'Sin descripción', ENT_QUOTES, 'UTF-8');
        $contractObject = htmlspecialchars($currentSeaceTender->contract_object ?? '—', ENT_QUOTES, 'UTF-8');
        $processType = htmlspecialchars($currentSeaceTender->process_type ?? '—', ENT_QUOTES, 'UTF-8');
        
        $html = '<div class="space-y-3">';
        $html .= '<div>';
        $html .= '<h3 class="text-lg font-bold">' . htmlspecialchars($baseCode, ENT_QUOTES, 'UTF-8') . '</h3>';
        $html .= '</div>';
        
        $html .= '<div class="space-y-1 text-sm text-gray-900 dark:text-white">';
        
        // Object Description
        if ($objectDescription && $objectDescription !== 'Sin descripción') {
            $html .= '<div class="flex items-start gap-2">';
            $html .= '<span class="font-extrabold underline text-lg">Objeto:</span>';
            $html .= '<span class="flex-1 italic text-sm">' . $objectDescription . '</span>';
            $html .= '</div>';
        }        
        
        // Contract Object y Process Type en línea
        $html .= '<div class="flex items-center gap-4 flex-wrap">';
        
        if ($contractObject && $contractObject !== '—') {
            $html .= '<div class="flex items-center gap-2">';
            $html .= '<span class="font-extrabold underline text-lg">Tipo:</span>';
            $html .= '<span class="italic text-sm">' . $contractObject . '</span>';
            $html .= '</div>';
        }        
        
        if ($processType && $processType !== '—') {
            $html .= '<div class="flex items-center gap-2">';
            $html .= '<span class="font-extrabold underline text-lg">Proceso:</span>';
            $html .= '<span class="italic text-sm">' . $processType . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return new HtmlString($html);
    }
    
    /**
     * Renderizar tabla HTML del historial
     * 
     * @param  Collection  $history  Colección de SeaceTender
     * @param  SeaceTender|null  $currentSeaceTender  El SeaceTender actual (más reciente)
     * @return HtmlString
     */
    public static function renderHistoryTable(Collection $history, ?SeaceTender $currentSeaceTender = null): HtmlString
    {
        $currentSeaceTenderId = $currentSeaceTender?->id;
        
        if ($history->isEmpty()) {
            return new HtmlString(
                '<div class="p-4 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-2 text-sm font-medium">No hay historial disponible</p>
                    <p class="text-xs text-gray-500">No se encontraron registros históricos para este proceso.</p>
                </div>'
            );
        }
        
        $html = '<div class="overflow-x-auto -mx-6 px-6">';
        $html .= '<table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">';
        $html .= '<thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-3 font-semibold">Nomenclatura</th>';
        $html .= '<th class="px-4 py-3 font-semibold text-center">Intento</th>';
        $html .= '<th class="px-4 py-3 font-semibold">Fecha</th>';
        $html .= '<th class="px-4 py-3 font-semibold">Hora</th>';
        $html .= '<th class="px-4 py-3 font-semibold text-right">VR/VE Cuantía de la Contratación</th>';
        $html .= '<th class="px-4 py-3 font-semibold">Reiniciado Desde</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($history as $seaceTender) {
            $isCurrent = $seaceTender->id === $currentSeaceTenderId;
            
            // Estilos de fila
            $rowClass = $isCurrent 
                ? 'bg-green-50 dark:bg-green-900/20' 
                : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors';
            
            $html .= '<tr class="' . $rowClass . '">';
            
            // Color para todos los campos de la fila actual
            $textColor = $isCurrent 
                ? 'text-primary-600 dark:text-primary-400' 
                : 'text-gray-600 dark:text-gray-400';
            
            // Nomenclatura - Color primary para el actual, gris para otros
            $identifier = htmlspecialchars($seaceTender->identifier, ENT_QUOTES, 'UTF-8');
            $identifierColor = $isCurrent 
                ? 'text-primary-600 dark:text-primary-400' 
                : 'text-gray-600 dark:text-gray-400';
            
            $html .= '<td class="px-4 py-4">';
            $html .= '<div class="font-bold ' . $identifierColor . '">';
            $html .= '<div class="max-w-md truncate">' . $identifier . '</div>';
            $html .= '</div>';
            $html .= '</td>';
            
            // Intento - Color primary para el actual
            $html .= '<td class="px-4 py-4 text-center">';
            $html .= '<div class="text-lg font-bold ' . $textColor . '">' . $seaceTender->code_attempt . '</div>';
            $html .= '</td>';
            
            // Fecha - Color primary para el actual
            $publishDate = $seaceTender->publish_date?->format('d/m/Y') ?? '—';
            $html .= '<td class="px-4 py-4">';
            $html .= '<div class="text-sm font-medium ' . $textColor . '">' . $publishDate . '</div>';
            $html .= '</td>';
            
            // Hora - Color primary para el actual
            $publishTime = $seaceTender->publish_date_time ? \Carbon\Carbon::parse($seaceTender->publish_date_time)->format('H:i') : '—';
            $html .= '<td class="px-4 py-4">';
            $html .= '<div class="text-sm font-medium ' . $textColor . '">' . $publishTime . '</div>';
            $html .= '</td>';
            
            // Valor Estimado - VR/VE Cuantía de la Contratación - Color primary para el actual
            $value = $seaceTender->estimated_referenced_value;
            $currency = $seaceTender->currency_name ?? 'PEN';
            $valueFormatted = $value !== null && $value > 0 
                ? number_format($value, 2, '.', ',') . ' ' . $currency
                : 'S/ 0.00';
            
            $html .= '<td class="px-4 py-4 text-right">';
            $html .= '<div class="text-base font-bold ' . $textColor . '">' . $valueFormatted . '</div>';
            $html .= '</td>';
            
            // Reiniciado Desde - Color primary para el actual
            $resumedFrom = htmlspecialchars($seaceTender->resumed_from ?? '—', ENT_QUOTES, 'UTF-8');
            $html .= '<td class="px-4 py-4">';
            $html .= '<div class="text-sm ' . $textColor . '">' . $resumedFrom . '</div>';
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return new HtmlString($html);
    }
    
    /**
     * Obtener información resumida del historial
     * 
     * @param  Collection  $history
     * @param  SeaceTender|null  $currentSeaceTender
     * @return array
     */
    public static function getHistorySummary(Collection $history, ?SeaceTender $currentSeaceTender = null): array
    {
        if ($history->isEmpty()) {
            return [
                'total' => 0,
                'attempts' => [],
                'latest' => null,
            ];
        }
        
        $attempts = $history->groupBy('code_attempt')->keys()->sort()->values()->toArray();
        
        return [
            'total' => $history->count(),
            'attempts' => $attempts,
            'latest' => $currentSeaceTender,
            'latest_attempt' => $currentSeaceTender?->code_attempt,
            'latest_date' => $currentSeaceTender?->publish_date?->format('d/m/Y'),
        ];
    }
}
