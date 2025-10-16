<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualUsuario extends Model
{
    use HasFactory;

    protected $table = 'manual_usuario';

    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'version',
        'link_videos',
        'subido_por',
    ];

    protected $casts = [
        'subido_por' => 'integer',
    ];

    /**
     * Relación con el usuario que subió el manual
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Obtiene el manual más reciente
     */
    public static function getLatest()
    {
        return static::latest()->first();
    }

    /**
     * Obtiene la URL pública del archivo PDF
     */
    public function getPdfUrlAttribute()
    {
        return asset('storage/' . $this->ruta_archivo);
    }

    /**
     * Verifica si existe un manual
     */
    public static function exists()
    {
        return static::count() > 0;
    }
}