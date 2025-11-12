<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SeaceTender;
use App\Models\SeaceTenderCurrent;

/**
 * Comando para sincronizar manualmente la tabla lookup seace_tender_current
 * 
 * Este comando actualiza la tabla lookup con el SeaceTender más reciente
 * por cada base_code. Útil para:
 * - Sincronización inicial después de migraciones
 * - Reparación de datos inconsistentes
 * - Actualización manual después de cambios masivos
 */
class SyncSeaceTenderCurrent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seace:sync-current 
                            {--force : Forzar sincronización completa incluso si ya existe lookup}
                            {--base-code= : Sincronizar solo un base_code específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza la tabla lookup seace_tender_current con el SeaceTender más reciente por base_code';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando sincronización de seace_tender_current...');
        
        $baseCode = $this->option('base-code');
        $force = $this->option('force');
        
        if ($baseCode) {
            // Sincronizar solo un base_code específico
            $this->syncBaseCode($baseCode, $force);
        } else {
            // Sincronizar todos los base_code
            $this->syncAll($force);
        }
        
        $this->info('✅ Sincronización completada');
        
        return Command::SUCCESS;
    }
    
    /**
     * Sincronizar un base_code específico
     */
    protected function syncBaseCode(string $baseCode, bool $force): void
    {
        $this->line("📌 Sincronizando base_code: {$baseCode}");
        
        // Verificar si existe lookup
        $current = SeaceTenderCurrent::find($baseCode);
        
        if ($current && !$force) {
            $this->warn("⚠️  Ya existe lookup para este base_code. Usa --force para re-sincronizar.");
            return;
        }
        
        // Obtener el más reciente por base_code
        $latest = SeaceTender::latestByBaseCode($baseCode)->first();
        
        if (!$latest) {
            $this->error("❌ No se encontró ningún SeaceTender con base_code: {$baseCode}");
            return;
        }
        
        // Actualizar o crear lookup
        SeaceTenderCurrent::updateLatest($baseCode, $latest->id);
        
        $this->info("✅ Lookup actualizado: {$baseCode} → SeaceTender #{$latest->id} ({$latest->identifier})");
    }
    
    /**
     * Sincronizar todos los base_code
     */
    protected function syncAll(bool $force): void
    {
        $this->line('📊 Obteniendo todos los base_code únicos...');
        
        // Obtener todos los base_code únicos de seace_tenders
        $baseCodes = SeaceTender::whereNotNull('base_code')
            ->distinct()
            ->pluck('base_code');
        
        $total = $baseCodes->count();
        $this->info("📈 Se encontraron {$total} base_code únicos");
        
        if ($total === 0) {
            $this->warn('⚠️  No se encontraron base_code para sincronizar');
            return;
        }
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($baseCodes as $baseCode) {
            // Obtener el más reciente por base_code
            $latest = SeaceTender::latestByBaseCode($baseCode)->first();
            
            if (!$latest) {
                $skipped++;
                $bar->advance();
                continue;
            }
            
            // Verificar si ya existe lookup
            $existing = SeaceTenderCurrent::find($baseCode);
            
            if ($existing && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }
            
            // Actualizar o crear lookup
            SeaceTenderCurrent::updateLatest($baseCode, $latest->id);
            
            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Resumen de sincronización:");
        $this->line("   📝 Creados: {$created}");
        $this->line("   🔄 Actualizados: {$updated}");
        $this->line("   ⏭️  Omitidos: {$skipped}");
        $this->line("   📊 Total procesados: {$total}");
    }
}
