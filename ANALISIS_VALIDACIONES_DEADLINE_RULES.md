# 📋 ANÁLISIS DETALLADO: SISTEMA DE VALIDACIONES DE TENDER_DEADLINE_RULES

## 🎯 RESUMEN EJECUTIVO

Este documento analiza en profundidad el sistema de validaciones de reglas de plazo (`tender_deadline_rules`) y su dinámica con los campos de fecha en el sistema de Tenders.

---

## 📊 ESTRUCTURA DE DATOS

### 1. Tabla `tender_deadline_rules`

#### Campos Principales:

-   **`from_stage`** (string, 10): Etapa origen del plazo (S1, S2, S3, S4)
-   **`to_stage`** (string, 10): Etapa destino del plazo (S1, S2, S3, S4)
-   **`from_field`** (string, 100): Campo de fecha origen (ej: `s1Stage.market_indagation_date`)
-   **`to_field`** (string, 100): Campo de fecha destino (ej: `s1Stage.certification_date`)
-   **`legal_days`** (integer): Días hábiles permitidos (NOTA: Actualmente se calculan como días calendario)
-   **`is_active`** (boolean): Si la regla está activa y se aplica
-   **`is_mandatory`** (boolean): Si es obligatoria (NOTA: Actualmente no se usa para prevenir guardado)
-   **`description`** (text, nullable): Descripción opcional de la regla

#### Índices:

-   `['from_stage', 'is_active']`
-   `['to_stage', 'is_active']`
-   `['from_stage', 'to_stage']`

---

## 🔄 FLUJO DE VALIDACIÓN

### 1. **Configuración de Reglas** (TenderDeadlineRuleResource)

**Ubicación**: `app/Filament/Resources/TenderDeadlineRuleResource.php`

**Funcionalidad**:

-   Solo SuperAdmin puede crear/editar/eliminar reglas
-   Las reglas se configuran mediante un formulario Filament
-   Validaciones del formulario:
    -   `from_stage` y `to_stage` son requeridos
    -   `from_field` y `to_field` son requeridos
    -   `legal_days` debe ser entre 1 y 365
    -   El campo destino no puede ser el mismo que el origen si están en la misma etapa

**Ejemplo de Regla**:

```php
from_stage: 'S1'
from_field: 's1Stage.market_indagation_date'
to_stage: 'S1'
to_field: 's1Stage.certification_date'
legal_days: 4
is_active: true
is_mandatory: true
```

---

### 2. **Aplicación de Validaciones en Formularios** (DeadlineHintHelper)

**Ubicación**: `app/Filament/Resources/TenderResource/Components/Shared/DeadlineHintHelper.php`

**Métodos Principales**:

#### a) `getHelperText()` - Muestra fecha programada

-   Busca reglas activas donde `to_field` coincide con el campo actual
-   Calcula la fecha programada: `fromDate + legal_days`
-   Muestra diferencia de días (excedido, dentro del plazo, o fecha anterior)

#### b) `getHint()` - Texto "Fecha Ejecutada"

-   Solo muestra si existe una regla válida (con campo origen presente)

#### c) `getHintIcon()` - Ícono check/x

-   ✅ `heroicon-m-check-circle` si es válido
-   ❌ `heroicon-m-x-circle` si es inválido

#### d) `getHintColor()` - Color del hint

-   `success` si es válido
-   `danger` si es inválido

#### e) `getHintIconTooltip()` - Tooltip con detalles

-   Muestra estado (cumplido/excedido/error)
-   Muestra información "Desde → Hasta"

#### f) `validateField()` - Lógica de validación principal

```php
// Busca reglas aplicables
$rules = TenderDeadlineRule::active()
    ->where('to_stage', $stageType)
    ->where('to_field', $fieldName)
    ->get();

// Para cada regla:
foreach ($rules as $rule) {
    $fromFieldValue = $get($rule->from_field);

    if (!$fromFieldValue) {
        continue; // Si no hay campo origen, se ignora
    }

    $fromDate = Carbon::parse($fromFieldValue);
    $currentDate = Carbon::parse($currentValue);

    // ⚠️ CALCULA DÍAS CALENDARIO (NO DÍAS HÁBILES)
    $calendarDays = self::calculateCalendarDays($fromDate, $currentDate);

    // Validación: si calendarDays <= legal_days → válido
    $ruleValid = $calendarDays <= $rule->legal_days;

    if (!$ruleValid) {
        $isValid = false; // Si alguna regla falla, el campo es inválido
    }
}
```

**⚠️ IMPORTANTE**:

-   La validación es **SOLO VISUAL** (hints, icons, tooltips)
-   **NO HAY VALIDACIÓN DEL LADO DEL SERVIDOR** que impida guardar
-   El usuario puede guardar incluso si las fechas exceden los plazos

---

### 3. **Uso en Componentes de Formulario**

**Ubicación**: `app/Filament/Resources/TenderResource/Components/S1PreparatoryTab.php`, `S2SelectionTab.php`, `S3ContractTab.php`, `S4ExecutionTab.php`

**Ejemplo de Uso**:

```php
DatePicker::make('s1Stage.certification_date')
    ->label('Certificación')
    ->helperText(fn (Forms\Get $get) =>
        Shared\DeadlineHintHelper::getHelperText($get, 'S1', 's1Stage.certification_date')
    )
    ->hint(fn (Forms\Get $get) =>
        Shared\DeadlineHintHelper::getHint($get, 'S1', 's1Stage.certification_date')
    )
    ->hintIcon(fn (Forms\Get $get) =>
        Shared\DeadlineHintHelper::getHintIcon($get, 'S1', 's1Stage.certification_date')
    )
    ->hintColor(fn (Forms\Get $get) =>
        Shared\DeadlineHintHelper::getHintColor($get, 'S1', 's1Stage.certification_date')
    )
    ->hintIconTooltip(fn (Forms\Get $get) =>
        Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S1', 's1Stage.certification_date')
    )
```

---

## 📝 CAMPOS INVOLUCRADOS POR ETAPA

### Etapa S1 (Preparatorias)

-   `s1Stage.request_presentation_date` - Presentación de Requerimiento
-   `s1Stage.market_indagation_date` - Indagación de Mercado
-   `s1Stage.certification_date` - Certificación
-   `s1Stage.provision_date` - Previsión
-   `s1Stage.approval_expedient_date` - Aprobación del Expediente
-   `s1Stage.selection_committee_date` - Designación del Comité
-   `s1Stage.administrative_bases_date` - Elaboración de Bases Administrativas
-   `s1Stage.approval_expedient_format_2` - Aprobación de Bases Administrativas Formato 2

### Etapa S2 (Selección)

-   `s2Stage.published_at` - Registro de Convocatoria en el SEACE
-   `s2Stage.participants_registration` - Registro de Participantes
-   `s2Stage.absolution_obs` - Absolución de Consultas y Observaciones
-   `s2Stage.base_integration` - Integración de las Bases
-   `s2Stage.offer_presentation` - Presentación de Propuestas
-   `s2Stage.offer_evaluation` - Calificación y Evaluación de Propuestas
-   `s2Stage.award_granted_at` - Otorgamiento de Buena Pro
-   `s2Stage.award_consent` - Consentimiento de Buena Pro
-   `s2Stage.appeal_date` - Apelación

### Etapa S3 (Contrato)

-   `s3Stage.doc_sign_presentation_date` - Presentación de Documentos de Suscripción
-   `s3Stage.contract_signing` - Suscripción del Contrato

### Etapa S4 (Ejecución)

-   `s4Stage.contract_signing` - Fecha de Suscripción del Contrato
-   `s4Stage.contract_vigency_date` - Fecha de Vigencia del Contrato

---

## 🔍 LÓGICA DE VALIDACIÓN DETALLADA

### 1. **Búsqueda de Reglas Aplicables**

```php
// En DeadlineHintHelper::validateField()
$rules = TenderDeadlineRule::active()
    ->where('to_stage', $stageType)      // Etapa del campo destino
    ->where('to_field', $fieldName)      // Nombre exacto del campo destino
    ->get();
```

**Nota**: Un campo puede tener **múltiples reglas** aplicables. Por ejemplo:

-   `s1Stage.certification_date` puede tener una regla desde `market_indagation_date` (4 días)
-   Y otra regla desde `request_presentation_date` (10 días)

**Comportamiento**: Si **cualquier regla falla**, el campo se marca como inválido.

---

### 2. **Cálculo de Días**

```php
// En DeadlineHintHelper::calculateCalendarDays()
private static function calculateCalendarDays(Carbon $fromDate, Carbon $toDate): int
{
    return $fromDate->diffInDays($toDate);
}
```

**⚠️ PROBLEMA IDENTIFICADO**:

-   El campo se llama `legal_days` (días hábiles)
-   Pero el cálculo usa `diffInDays()` que calcula **días calendario** (incluye fines de semana)
-   Los comentarios en el código dicen: "NOTA: Los días hábiles se implementarán en una fase posterior"

---

### 3. **Validación de Reglas**

```php
// Para cada regla encontrada:
$calendarDays = self::calculateCalendarDays($fromDate, $currentDate);
$ruleValid = $calendarDays <= $rule->legal_days;

if (!$ruleValid) {
    $isValid = false; // Si alguna regla falla, el campo es inválido
}
```

**Lógica**:

-   Si `calendarDays <= legal_days` → ✅ Válido
-   Si `calendarDays > legal_days` → ❌ Inválido
-   Si `currentDate < fromDate` → ⚠️ Error de lógica (fecha anterior a origen)

---

### 4. **Validación de Regla Válida (`hasValidRule`)**

```php
private static function hasValidRule(Forms\Get $get, string $stageType, string $fieldName): bool
{
    $rules = TenderDeadlineRule::active()
        ->where('to_stage', $stageType)
        ->where('to_field', $fieldName)
        ->get();

    if ($rules->isEmpty()) {
        return false;
    }

    // Verificar si al menos una regla tiene el campo origen con valor
    foreach ($rules as $rule) {
        $fromFieldValue = $get($rule->from_field);

        if ($fromFieldValue) {
            return true; // Si hay al menos una regla con campo origen presente
        }
    }

    return false;
}
```

**Propósito**: Evita mostrar hints cuando:

-   No hay reglas configuradas
-   Ninguna regla tiene el campo origen con valor (campos opcionales)

---

## ⚠️ PROBLEMAS Y LIMITACIONES IDENTIFICADAS

### 1. **Validación Solo Visual**

-   **Problema**: Las validaciones son solo visuales (hints, icons, tooltips)
-   **Impacto**: Los usuarios pueden guardar datos con fechas que exceden los plazos legales
-   **Solución Potencial**: Implementar validación del lado del servidor usando `is_mandatory`

### 2. **Días Calendario vs Días Hábiles**

-   **Problema**: El campo se llama `legal_days` (días hábiles) pero se calculan días calendario
-   **Impacto**: Las validaciones no reflejan correctamente los plazos legales reales
-   **Solución Potencial**: Implementar cálculo de días hábiles (excluyendo fines de semana y feriados)

### 3. **Campo `is_mandatory` No Se Usa**

-   **Problema**: El campo `is_mandatory` existe pero no se usa para prevenir guardado
-   **Impacto**: No hay diferencia entre reglas obligatorias y opcionales en la práctica
-   **Solución Potencial**: Usar `is_mandatory` para decidir si se debe prevenir el guardado

### 4. **Múltiples Reglas para un Campo**

-   **Problema**: Si un campo tiene múltiples reglas y solo una falla, el campo se marca como inválido
-   **Impacto**: Puede ser confuso para el usuario saber qué regla específica está fallando
-   **Solución Potencial**: Mejorar el mensaje de error para mostrar qué regla específica falla

### 5. **Falta de Validación en Guardado**

-   **Problema**: No hay validación en el modelo `Tender` o en los eventos de guardado
-   **Impacto**: Los datos pueden persistirse incluso si violan las reglas legales
-   **Solución Potencial**: Agregar validación en el modelo usando `rules()` o en eventos del modelo

---

## 🔄 FLUJO COMPLETO DE VALIDACIÓN

```
1. Usuario abre formulario de Tender
   ↓
2. Usuario selecciona/completa campo de fecha origen (ej: market_indagation_date)
   ↓
3. Sistema busca reglas activas donde to_field = campo destino
   ↓
4. Si encuentra reglas:
   a. Verifica si campo origen tiene valor (hasValidRule)
   b. Si tiene valor, calcula fecha programada y muestra helperText
   c. Calcula diferencia de días (días calendario)
   d. Valida: calendarDays <= legal_days
   e. Muestra hintIcon (check/x) y hintColor (success/danger)
   f. Muestra tooltip con detalles
   ↓
5. Usuario completa campo de fecha destino
   ↓
6. Sistema recalcula validación en tiempo real
   ↓
7. Usuario guarda formulario
   ↓
8. ⚠️ NO HAY VALIDACIÓN DEL SERVIDOR - Los datos se guardan incluso si son inválidos
```

---

## 📊 EJEMPLOS DE REGLAS REALES

### Ejemplo 1: Regla Intra-Etapa (Misma Etapa)

```php
from_stage: 'S1'
from_field: 's1Stage.market_indagation_date'
to_stage: 'S1'
to_field: 's1Stage.certification_date'
legal_days: 4
```

**Significado**: Desde "Indagación de Mercado" hasta "Certificación" deben pasar máximo 4 días hábiles.

### Ejemplo 2: Regla Inter-Etapa (Diferentes Etapas)

```php
from_stage: 'S1'
from_field: 's1Stage.approval_expedient_format_2'
to_stage: 'S2'
to_field: 's2Stage.published_at'
legal_days: 5
```

**Significado**: Desde "Aprobación de Bases Administrativas Formato 2" (S1) hasta "Registro de Convocatoria en el SEACE" (S2) deben pasar máximo 5 días hábiles.

---

## 🎯 CONCLUSIONES

1. **Sistema Funcional pero Incompleto**:

    - Las validaciones visuales funcionan correctamente
    - Falta validación del lado del servidor

2. **Discrepancia Semántica**:

    - `legal_days` implica días hábiles pero se calculan días calendario

3. **Campo `is_mandatory` Infrautilizado**:

    - Existe pero no se usa para prevenir guardado

4. **Mejoras Necesarias**:
    - Implementar validación del servidor
    - Implementar cálculo de días hábiles
    - Usar `is_mandatory` para prevenir guardado cuando sea necesario
    - Mejorar mensajes de error para múltiples reglas

---

## 📚 ARCHIVOS RELEVANTES

-   `app/Models/TenderDeadlineRule.php` - Modelo de reglas
-   `app/Filament/Resources/TenderDeadlineRuleResource.php` - CRUD de reglas
-   `app/Filament/Resources/TenderResource/Components/Shared/DeadlineHintHelper.php` - Helper de validación
-   `app/Services/TenderFieldExtractor.php` - Extracción de campos por etapa
-   `database/migrations/2025_09_22_193704_create_tender_deadline_rules_table.php` - Migración inicial
-   `database/migrations/2025_10_01_153451_add_from_stage_and_to_stage_to_tender_deadline_rules_table.php` - Migración para soportar reglas inter-etapa

---

**Fecha de Análisis**: 2025-01-XX
**Versión del Sistema**: Actual
