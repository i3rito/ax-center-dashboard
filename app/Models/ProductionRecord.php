<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRecord extends Model
{
    protected $fillable = [
        'product_id',
        'production_line_id',
        'produced_qty',
        'defective_qty',
        'recorded_at',
    ];

    protected $casts = [
        'produced_qty' => 'integer',
        'defective_qty' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productionLine()
    {
        return $this->belongsTo(ProductionLine::class);
    }
}
