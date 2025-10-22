<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Colores Personalizados para Etapas de Procedimientos
    |--------------------------------------------------------------------------
    |
    | Este archivo define los colores personalizados para las diferentes
    | etapas de los procedimientos de selección. Estos colores se pueden
    | usar en toda la aplicación de manera consistente.
    |
    */

    'stages' => [
        'preparatorias' => [
            'name' => 'Actuaciones Preparatorias',
            'color' => 'info', // Azul
            'hex' => '#3B82F6',
            'description' => 'Etapa inicial de preparación'
        ],
        'seleccion' => [
            'name' => 'Procedimiento de Selección',
            'color' => 'warning', // Amarillo
            'hex' => '#F59E0B',
            'description' => 'Etapa de selección de participantes'
        ],
        'contrato' => [
            'name' => 'Suscripción del Contrato',
            'color' => 'custom-orange', // Naranja personalizado
            'hex' => '#F97316',
            'description' => 'Etapa de suscripción del contrato'
        ],
        'ejecucion' => [
            'name' => 'Tiempo de Ejecución',
            'color' => 'success', // Verde
            'hex' => '#10B981',
            'description' => 'Etapa de ejecución del contrato'
        ],
        'no_iniciado' => [
            'name' => 'No iniciado',
            'color' => 'gray',
            'hex' => '#6B7280',
            'description' => 'Procedimiento sin iniciar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo de Etapas
    |--------------------------------------------------------------------------
    |
    | Mapeo entre los nombres de las etapas y sus configuraciones
    |
    */
    'stage_mapping' => [
        'E1 - Actuaciones Preparatorias' => 'preparatorias',
        'Actuaciones Preparatorias' => 'preparatorias',
        'E2 - Procedimiento de Selección' => 'seleccion',
        'Procedimiento de Selección' => 'seleccion',
        'E3 - Suscripción del Contrato' => 'contrato',
        'Suscripción del Contrato' => 'contrato',
        'E4 - Ejecución' => 'ejecucion',
        'Tiempo de Ejecución' => 'ejecucion',
        'No iniciado' => 'no_iniciado',
    ]
];
