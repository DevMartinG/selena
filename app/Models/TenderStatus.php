<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStatus extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'code',
        'name',
        'category',
        'is_active',
    ];

    /**
     * Casts para los campos
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con Tender
     */
    public function tenders()
    {
        return $this->hasMany(Tender::class, 'tender_status_id');
    }

    /**
     * Scope para estados activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para estados por categoría
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para buscar por código
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope para estados válidos (excluye el estado por defecto "Sin Estado")
     * Solo para uso en formularios de creación/edición
     */
    public function scopeValidForForm($query)
    {
        return $query->where('is_active', true)
                    ->where('code', '!=', '--');
    }
}
