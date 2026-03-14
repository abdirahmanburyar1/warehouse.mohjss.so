<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplyClass extends Model
{
    protected $fillable = [
        'supply_class',
        'category',
        'source',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
