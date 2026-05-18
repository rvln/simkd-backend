<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ReportStatusEnum;

class VisitReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'visit_id',
        'user_id',
        'content',
        'admin_title',
        'image_path',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'status'     => ReportStatusEnum::class,
        'image_path' => 'array',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
