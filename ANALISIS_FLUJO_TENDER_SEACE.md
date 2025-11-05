# 🔍 ANÁLISIS COMPLETO: Flujo Tender ↔ SeaceTender - Sincronización y Coherencia

## 📋 RESUMEN EJECUTIVO

Este documento analiza el flujo completo entre la creación/edición de `Tender` y las importaciones de `SeaceTender`, identificando inconsistencias y proponiendo un plan de optimización.

---

## 🔄 FLUJO ACTUAL

### **1. Creación de Tender desde SeaceTender**

```
Usuario selecciona SeaceTenderCurrent en GeneralInfoTab
    ↓
Se copian campos del SeaceTender relacionado:
    • entity_name
    • process_type_id
    • contract_object
    • object_description
    • estimated_referenced_value
    • currency_name
    • tender_status_id
    • identifier
    ↓
Se establece seace_tender_current_id = base_code
    ↓
Se regeneran campos derivados (code_short_type, code_type, etc.)
    ↓
Tender::creating() evento:
    • Asigna created_by
    • Genera identifier si es necesario
    • Regenera campos derivados
    • NO sincroniza con SeaceTenderCurrent
```

**✅ Funciona bien en creación inicial**

---

### **2. Edición de Tender**

```
Usuario edita Tender existente
    ↓
Cambios manuales en formulario
    ↓
Tender::updating() evento:
    • Asigna updated_by
    • Si cambia identifier, regenera campos derivados
    • NO verifica si SeaceTenderCurrent cambió
    • NO sincroniza campos desde SeaceTenderCurrent
```

**⚠️ Problema: No hay sincronización automática con SeaceTenderCurrent**

---

### **3. Importación de SeaceTender (Nuevo registro)**

```
Importación Excel detecta nuevo SeaceTender
    ↓
SeaceTender::creating() evento:
    • Extrae base_code
    • Genera campos derivados
    ↓
SeaceTender::created() evento:
    • Llama syncCurrentLookup()
    • Actualiza seace_tender_current con el más reciente
    ↓
✅ Lookup actualizado
❌ Tenders existentes NO se actualizan automáticamente
```

**⚠️ Problema: Tenders quedan con datos desactualizados**

---

### **4. Importación de SeaceTender (Actualización)**

```
Importación Excel detecta SeaceTender existente
    ↓
Se comparan campos y se actualizan los que cambiaron:
    • entity_name
    • contract_object
    • object_description
    • estimated_referenced_value
    • currency_name
    • resumed_from
    ↓
SeaceTender::updated() evento:
    • Verifica si cambió code_attempt, publish_date, publish_date_time
    • Si cambió, llama syncCurrentLookup()
    • Actualiza seace_tender_current si hay nuevo más reciente
    ↓
✅ Lookup actualizado si corresponde
❌ Tenders existentes NO se actualizan automáticamente
```

**⚠️ Problema: Tenders mantienen valores antiguos incluso si el lookup cambió**

---

## 🚨 PROBLEMAS IDENTIFICADOS

### **Problema 1: Falta de Sincronización Automática**

**Situación:**

-   Cuando se importa un `SeaceTender` nuevo o actualizado que cambia el `seace_tender_current` lookup
-   Los `Tender` existentes que apuntan al mismo `base_code` NO se actualizan automáticamente
-   Los campos del `Tender` quedan desactualizados respecto al `SeaceTender` más reciente

**Ejemplo:**

```
Tender #1 creado con:
    • seace_tender_current_id = "COMPRE-COMPRE-84-2025-GR PUNO/OC"
    • identifier = "COMPRE-COMPRE-84-2025-GR PUNO/OC-2"
    • estimated_referenced_value = 100000

Se importa nuevo SeaceTender:
    • identifier = "COMPRE-COMPRE-84-2025-GR PUNO/OC-3"
    • estimated_referenced_value = 150000
    • code_attempt = 3 (más reciente)

Resultado:
    ✅ seace_tender_current se actualiza → apunta a SeaceTender #3
    ❌ Tender #1 mantiene estimated_referenced_value = 100000 (valor antiguo)
    ❌ Tender #1 NO sabe que hay datos más recientes disponibles
```

---

### **Problema 2: Campos que Deberían Sincronizarse vs Manuales**

**Campos que PROBABLEMENTE deberían sincronizarse automáticamente:**

-   `entity_name` - Puede cambiar si la entidad cambia
-   `contract_object` - Puede actualizarse
-   `object_description` - Puede actualizarse
-   `estimated_referenced_value` - Puede cambiar significativamente
-   `currency_name` - Raro que cambie, pero posible
-   `identifier` - Puede cambiar si hay nuevo intento (¿debería actualizarse?)
-   `tender_status_id` - Puede cambiar según estado en SEACE

**Campos que NO deberían sincronizarse (manuales del usuario):**

-   `observation` - Comentarios del usuario
-   `selection_comittee` - Información específica del proceso local
-   `with_identifier` - Flag interno
-   Campos de stages (S1, S2, S3, S4) - Progreso interno del proceso
-   Campos de auditoría (`created_by`, `updated_by`) - Historial

**Campos derivados (se regeneran automáticamente):**

-   `code_short_type`, `code_type`, `code_year`, `code_sequence`, `code_attempt`, `code_full`
-   `process_type_id` - Se deriva del identifier

---

### **Problema 3: No Hay Evento de Sincronización cuando Cambia Lookup**

**Situación actual:**

-   `SeaceTenderCurrent::updateLatest()` actualiza el lookup
-   Pero NO hay evento que notifique a los `Tender` afectados
-   Los `Tender` no saben que el lookup cambió

**Lo que falta:**

-   Evento cuando `seace_tender_current.latest_seace_tender_id` cambia
-   Notificar a todos los `Tender` con ese `base_code`
-   Opción de sincronizar campos automáticamente (con configuración)

---

### **Problema 4: Búsqueda en GeneralInfoTab Puede Mostrar Datos Desactualizados**

**Situación:**

-   `GeneralInfoTab` busca en `SeaceTenderCurrent`
-   Siempre muestra el más reciente ✅
-   Pero al seleccionar, los campos se copian al formulario
-   Si el usuario edita manualmente, esos cambios NO se sincronizan después

---

### **Problema 5: Conflicto entre seace_tender_id y seace_tender_current_id**

**Situación actual:**

-   `Tender` tiene ambos campos:
    -   `seace_tender_id` (FK directa, deprecated)
    -   `seace_tender_current_id` (FK a lookup, nuevo)
-   `GeneralInfoTab` solo establece `seace_tender_current_id`
-   `seace_tender_id` puede quedar NULL o apuntar a registro antiguo

**¿Deberíamos:**

-   Mantener `seace_tender_id` sincronizado con el `latest_seace_tender_id` del lookup?
-   O eliminar `seace_tender_id` completamente en el futuro?

---

## 💡 PLAN DE OPTIMIZACIÓN PROPUESTO

### **Fase 1: Establecer Eventos de Sincronización**

**Objetivo:** Detectar cuando el lookup cambia y notificar a Tenders afectados

**Implementación:**

```php
// En SeaceTenderCurrent::updateLatest()
public static function updateLatest(string $baseCode, int $seaceTenderId): self
{
    $current = self::find($baseCode);
    $oldSeaceTenderId = $current?->latest_seace_tender_id;

    $updated = self::updateOrCreate(
        ['base_code' => $baseCode],
        [
            'latest_seace_tender_id' => $seaceTenderId,
            'updated_at' => now(),
        ]
    );

    // Si cambió el SeaceTender referenciado, disparar evento
    if ($oldSeaceTenderId && $oldSeaceTenderId !== $seaceTenderId) {
        event(new SeaceTenderCurrentUpdated($baseCode, $oldSeaceTenderId, $seaceTenderId));
    }

    return $updated;
}
```

---

### **Fase 2: Listener para Actualizar Tenders**

**Objetivo:** Sincronizar campos automáticamente cuando el lookup cambia

**Implementación:**

```php
// Listener: SyncTendersWhenSeaceTenderCurrentUpdated
class SyncTendersWhenSeaceTenderCurrentUpdated
{
    public function handle(SeaceTenderCurrentUpdated $event)
    {
        // Obtener todos los Tenders con ese base_code
        $tenders = Tender::where('seace_tender_current_id', $event->baseCode)->get();

        // Obtener el nuevo SeaceTender más reciente
        $latestSeaceTender = SeaceTender::find($event->newSeaceTenderId);

        if (!$latestSeaceTender) {
            return;
        }

        // Campos que se sincronizan automáticamente
        $syncFields = [
            'entity_name',
            'contract_object',
            'object_description',
            'estimated_referenced_value',
            'currency_name',
            'tender_status_id', // Opcional: puede que queramos mantener el estado manual
        ];

        foreach ($tenders as $tender) {
            // Verificar si el Tender tiene "auto-sync" habilitado
            // Podríamos agregar un campo boolean "auto_sync_from_seace" al Tender
            if ($tender->auto_sync_from_seace ?? true) {
                $updates = [];

                foreach ($syncFields as $field) {
                    // Solo actualizar si el campo NO fue modificado manualmente
                    // (podríamos usar un campo "last_manual_update_at" por campo)
                    if ($tender->shouldSyncField($field)) {
                        $updates[$field] = $latestSeaceTender->$field;
                    }
                }

                if (!empty($updates)) {
                    // Actualizar sin disparar eventos de auditoría (updated_by)
                    $tender->updateQuietly($updates);
                }
            }
        }
    }
}
```

---

### **Fase 3: Control de Sincronización por Campo**

**Objetivo:** Permitir al usuario controlar qué campos se sincronizan automáticamente

**Opciones:**

**Opción A: Flag global por Tender**

```php
// En tenders table
$table->boolean('auto_sync_from_seace')->default(true);
```

**Opción B: Timestamps por campo (más granular)**

```php
// En tenders table
$table->timestamp('entity_name_synced_at')->nullable();
$table->timestamp('contract_object_synced_at')->nullable();
// ... etc

// Lógica: Si el campo fue modificado manualmente después de la última sync,
// NO se sincroniza automáticamente
```

**Opción C: Híbrido (recomendado)**

```php
// Flag global + timestamps para campos críticos
$table->boolean('auto_sync_from_seace')->default(true);
$table->timestamp('last_manual_update_at')->nullable();

// Si last_manual_update_at > SeaceTenderCurrent.updated_at,
// NO sincronizar automáticamente (usuario hizo cambios manuales recientes)
```

---

### **Fase 4: Actualizar identifier Cuando Hay Nuevo Intent**

**Pregunta crítica:** ¿Deberíamos actualizar el `identifier` del Tender cuando hay un nuevo `code_attempt`?

**Ejemplo:**

```
Tender creado con:
    • identifier = "COMPRE-COMPRE-84-2025-GR PUNO/OC-2"

Se importa:
    • identifier = "COMPRE-COMPRE-84-2025-GR PUNO/OC-3"

¿Deberíamos actualizar el Tender a identifier = "...-3"?
```

**Respuesta:** **PROBABLEMENTE NO**, porque:

-   El `identifier` del Tender puede ser parte de documentación interna
-   Cambiar el identifier podría romper referencias externas
-   Pero SÍ deberíamos mostrar una advertencia/notificación al usuario

**Alternativa:** Mostrar badge/notificación cuando hay datos más recientes disponibles

---

### **Fase 5: Sincronización Manual desde UI**

**Objetivo:** Permitir al usuario sincronizar manualmente desde el formulario

**Implementación en GeneralInfoTab:**

```php
// Botón "Sincronizar con SEACE más reciente"
Action::make('sync_from_seace')
    ->label('Sincronizar con SEACE más reciente')
    ->icon('heroicon-m-arrow-path')
    ->color('info')
    ->visible(fn ($record) => $record?->seace_tender_current_id)
    ->action(function ($record) {
        $current = SeaceTenderCurrent::find($record->seace_tender_current_id);
        $latestSeaceTender = $current?->seaceTender;

        if (!$latestSeaceTender) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el SeaceTender más reciente')
                ->danger()
                ->send();
            return;
        }

        // Sincronizar campos
        $record->update([
            'entity_name' => $latestSeaceTender->entity_name,
            'contract_object' => $latestSeaceTender->contract_object,
            // ... otros campos
        ]);

        Notification::make()
            ->title('Sincronizado')
            ->body('Los campos se han actualizado desde SEACE')
            ->success()
            ->send();
    })
```

---

## 🎯 DECISIONES A TOMAR

### **1. ¿Sincronización Automática por Defecto?**

**Opción A:** Sí, siempre sincronizar automáticamente

-   ✅ Datos siempre actualizados
-   ❌ Puede sobrescribir cambios manuales del usuario

**Opción B:** No, solo sincronizar manualmente o con flag

-   ✅ Respeta cambios manuales del usuario
-   ❌ Requiere acción del usuario para mantener datos actualizados

**Opción C:** Híbrido - Sincronizar automáticamente SOLO si el usuario no ha hecho cambios manuales recientes

-   ✅ Balance entre automatización y control
-   ⚠️ Más complejo de implementar

**Recomendación:** **Opción C (Híbrido)**

---

### **2. ¿Qué Campos Sincronizar Automáticamente?**

**Campos críticos (SIEMPRE sincronizar):**

-   `entity_name` ✅
-   `contract_object` ✅
-   `object_description` ✅
-   `estimated_referenced_value` ✅

**Campos opcionales (sincronizar con cuidado):**

-   `currency_name` - Raro que cambie, pero posible
-   `tender_status_id` - Puede cambiar, pero el usuario puede tener un estado diferente

**Campos que NO sincronizar:**

-   `identifier` - Mantener el original del Tender
-   `observation` - Comentarios del usuario
-   `selection_comittee` - Información local
-   Campos de stages - Progreso interno

---

### **3. ¿Actualizar seace_tender_id cuando Cambia el Lookup?**

**Opción A:** Sí, mantener sincronizado

```php
// Cuando se actualiza lookup, también actualizar seace_tender_id
$tender->seace_tender_id = $latestSeaceTender->id;
```

**Opción B:** No, dejar deprecated

-   `seace_tender_id` queda para compatibilidad
-   Solo usar `seace_tender_current_id`

**Recomendación:** **Opción B** - Mantener `seace_tender_id` deprecated, eliminar en versión futura

---

## 📝 PLAN DE IMPLEMENTACIÓN SUGERIDO

### **Paso 1: Agregar Campo de Control de Sincronización**

```php
// Migración
Schema::table('tenders', function (Blueprint $table) {
    $table->boolean('auto_sync_from_seace')->default(true)->after('seace_tender_current_id');
    $table->timestamp('last_manual_update_at')->nullable()->after('auto_sync_from_seace');
});
```

---

### **Paso 2: Crear Evento y Listener**

```php
// Event: SeaceTenderCurrentUpdated
class SeaceTenderCurrentUpdated
{
    public function __construct(
        public string $baseCode,
        public int $oldSeaceTenderId,
        public int $newSeaceTenderId
    ) {}
}

// Listener: SyncTendersWhenSeaceTenderCurrentUpdated
```

---

### **Paso 3: Implementar Lógica de Sincronización**

```php
// En Tender model
public function shouldSyncField(string $field): bool
{
    // Si auto_sync está desactivado, no sincronizar
    if (!$this->auto_sync_from_seace) {
        return false;
    }

    // Si el usuario hizo cambios manuales recientes, no sincronizar
    if ($this->last_manual_update_at &&
        $this->last_manual_update_at > $this->seaceTenderCurrent->updated_at) {
        return false;
    }

    return true;
}
```

---

### **Paso 4: Agregar Botón de Sincronización Manual en UI**

```php
// En GeneralInfoTab o TenderResource
Action::make('sync_from_seace')
    ->label('Sincronizar con SEACE')
    ->icon('heroicon-m-arrow-path')
    ->action(...)
```

---

### **Paso 5: Mostrar Notificación cuando Hay Datos Más Recientes**

```php
// En TenderResource table o form
// Mostrar badge si hay datos más recientes disponibles
if ($record->hasNewerSeaceTenderAvailable()) {
    // Mostrar badge/icono
}
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

-   [ ] Agregar campo `auto_sync_from_seace` a `tenders`
-   [ ] Agregar campo `last_manual_update_at` a `tenders`
-   [ ] Crear Event `SeaceTenderCurrentUpdated`
-   [ ] Crear Listener `SyncTendersWhenSeaceTenderCurrentUpdated`
-   [ ] Implementar método `shouldSyncField()` en Tender
-   [ ] Actualizar `SeaceTenderCurrent::updateLatest()` para disparar evento
-   [ ] Agregar botón de sincronización manual en UI
-   [ ] Agregar método `hasNewerSeaceTenderAvailable()` en Tender
-   [ ] Mostrar badge/notificación cuando hay datos más recientes
-   [ ] Tests para sincronización automática
-   [ ] Tests para sincronización manual
-   [ ] Tests para respetar cambios manuales

---

## 🎓 CONCLUSIÓN

El flujo actual funciona bien para la creación inicial, pero **falta sincronización automática** cuando se importan nuevos `SeaceTender`.

La solución propuesta es **híbrida**: sincronizar automáticamente solo cuando el usuario no ha hecho cambios manuales recientes, con opción de sincronización manual desde la UI.

Esto garantiza:

-   ✅ Datos siempre actualizados cuando es seguro
-   ✅ Respeto por cambios manuales del usuario
-   ✅ Control explícito del usuario sobre la sincronización
-   ✅ Clean code y mantenibilidad
