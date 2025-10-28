<?php

namespace App\Console\Commands;

use App\Models\SeaceTender;
use Illuminate\Console\Command;

class SeaceTendersUpdateBaseCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seace-tenders:update-base-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el campo base_code en todos los registros de seace_tenders existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Actualizando base_code en registros de seace_tenders...');
        $this->newLine();

        $tenders = SeaceTender::whereNull('base_code')->orWhere('base_code', '')->get();

        if ($tenders->isEmpty()) {
            $this->info('✅ No hay registros para actualizar.');
            return 0;
        }

        $this->info("📊 Total de registros a actualizar: {$tenders->count()}");
        $this->newLine();

        $bar = $this->output->createProgressBar($tenders->count());
        $bar->start();

        $updated = 0;
        foreach ($tenders as $tender) {
            // Extraer base_code de identifier original
            $rawBaseCode = SeaceTender::extractBaseCode($tender->identifier);
            
            // Normalizar como se hace en el modelo (igual que code_full)
            $baseCode = $rawBaseCode ? SeaceTender::normalizeIdentifier($rawBaseCode) : null;

            if ($baseCode) {
                $tender->update(['base_code' => $baseCode]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Proceso completado. Registros actualizados: {$updated}");
        return 0;
    }
}
