<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetApprovalItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'asset_tag',
        'asset_name',
        'serial_number',
        'asset_category_id',
        'asset_type_id',
        'assignee_id',
        'status',
        'original_value',
        'fund_source_id',
        'acquisition_date',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'acquisition_date' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Assignee::class, 'assignee_id');
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function getDepreciationDataForResource(): array
    {
        return [
            'current_value' => (float) $this->original_value,
            'accumulated_depreciation' => 0,
            'has_depreciation' => false,
            'annual_depreciation' => null,
            'ytd_depreciation' => null,
            'remaining_life_years' => null,
            'replacement_year' => null,
        ];
    }
}
