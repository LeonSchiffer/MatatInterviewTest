<?php

namespace App\Models;

class Order extends BaseModel
{

    const STATUS_COMPLETED = "completed";
    const STATUS_PROCESSING = "processing";
    const STATUS_PENDING = "pending";

    protected $casts = [
        "billing" => "array",
        "shipping" => "array",
    ];

    public function lineItems()
    {
        return $this->hasMany(LineItem::class);
    }
}
