<?php

namespace App\Models;

class LineItem extends BaseModel
{
    protected $cast = [
        "taxes" => "array",
        "meta_data" => "array",
        "image" => "array",
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
