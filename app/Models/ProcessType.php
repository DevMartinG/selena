<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessType extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'code_short_type',
        'description_short_type',
        'year',
    ];

    /**
     * Relación con Tender
     */
    public function tenders()
    {
        return $this->hasMany(Tender::class, 'process_type', 'description_short_type');
    }

    /**
     * Scope para buscar por code_short_type
     */
    public function scopeByCodeShortType($query, string $codeShortType)
    {
        return $query->where('code_short_type', $codeShortType);
    }
}
