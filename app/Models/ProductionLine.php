<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionLine extends Model
{
    protected $fillable = ['name', 'code'];

    public function productionRecords()
    {
        return $this->hasMany(ProductionRecord::class);
    }
}
