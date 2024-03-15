<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OrderService
{
    private $columns_to_sync = ["id","number","order_key","status","date_created","total","customer_id","customer_note","billing","shipping"];

    public function formatOrdersForSync(Collection $orders): array
    {
        // return $orders->toArray();
        $data = [
            "orders" => [],
            "line_items" => []
        ];
        foreach ($orders as $order) {
            $data["orders"][] = [
                ...Arr::only($order, $this->columns_to_sync),
                "billing" => json_encode($order["billing"]),
                "shipping" => json_encode($order["shipping"])
            ];
            foreach (($order["line_items"] ?? []) as $line_item) {
                $data["line_items"][] = [
                    ...$line_item,
                    "order_id" => $order["id"],
                    "taxes" => json_encode($line_item["taxes"]),
                    "meta_data" => json_encode($line_item["meta_data"]),
                    "image" => json_encode($line_item["image"]),
                ];
            }
        }
        return $data;
    }
}
