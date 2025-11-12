<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla Lookup: Mapea base_code → latest_seace_tender_id
 * 
 * Esta tabla actúa como "cache" o "vista materializada" que siempre contiene
 * el SeaceTender más reciente por base_code. Se actualiza automáticamente
 * cuando se crean/actualizan registros en seace_tenders.
 */
class SeaceTenderCurrent extends Model
{
    use HasFactory;

    protected $table = 'seace_tender_current';
    
    /**
     * base_code es la PRIMARY KEY (no es auto-increment)
     */
    protected $primaryKey = 'base_code';
    
    public $incrementing = false;
    
    protected $keyType = 'string';
    
    /**
     * Solo updated_at (no created_at)
     */
    public $timestamps = false;
    
    protected $fillable = [
        'base_code',
        'latest_seace_tender_id',
    ];
    
    protected $casts = [
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con SeaceTender (el más reciente)
     */
    public function seaceTender(): BelongsTo
    {
        return $this->belongsTo(SeaceTender::class, 'latest_seace_tender_id');
    }
    
    /**
     * Actualizar o crear entrada para un base_code
     * 
     * @param  string  $baseCode  El código base del proceso
     * @param  int  $seaceTenderId  El ID del SeaceTender más reciente
     * @return self
     */
    public static function updateLatest(string $baseCode, int $seaceTenderId): self
    {
        return self::updateOrCreate(
            ['base_code' => $baseCode],
            [
                'latest_seace_tender_id' => $seaceTenderId,
                'updated_at' => now(),
            ]
        );
    }
    
    /**
     * Obtener el SeaceTender más reciente por base_code
     * 
     * @param  string  $baseCode  El código base del proceso
     * @return SeaceTender|null
     */
    public static function getLatestSeaceTender(string $baseCode): ?SeaceTender
    {
        $current = self::find($baseCode);
        
        return $current?->seaceTender;
    }
    
    /**
     * Obtener el ID del SeaceTender más reciente por base_code
     * 
     * @param  string  $baseCode  El código base del proceso
     * @return int|null
     */
    public static function getLatestSeaceTenderId(string $baseCode): ?int
    {
        $current = self::find($baseCode);
        
        return $current?->latest_seace_tender_id;
    }
}
