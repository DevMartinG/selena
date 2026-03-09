<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderStageS2Completed extends Model
{
    use HasFactory;

    protected $table = 'tender_stage_s2_completed';
    public $timestamps = false; // ya tenemos completed_at manual

    protected $fillable = [
        'tender_stage_id',
        'field_name',
        'user_id',
        'completed_at'
    ];

    public function tenderStage()
    {
        return $this->belongsTo(TenderStageS2SelectionProcess::class, 'tender_stage_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}