<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meta extends Model
{
    protected $table = 'metas';

    protected $fillable = [
        'anio',
        'codmeta',
        'nombre',
        'desmeta',
        'cui',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];


    public function users()
    {
        return $this->belongsToMany(User::class, 'meta_user'); // RELACION CON LA TABLA PIVOT META-USER
    }

}