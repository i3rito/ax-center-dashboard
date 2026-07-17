<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'slug'];

    public function productionRecords()
    {
        return $this->hasMany(ProductionRecord::class);
    }
}
