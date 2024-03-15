<?php

namespace App\Repositories;

use App\Exceptions\Order\OrderApiErrorException;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\LineItem;
use App\Services\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\WooCommerceApiRepositoryInterface;

/**
 * @implements OrderRepositoryInterface
 */
class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(private WooCommerceApiRepositoryInterface $woocommerce, private OrderService $order_service)
    {
    }

    /**
     * Gets the list of orders from the external WooCommerce API
     * @param Carbon\Carbon $start_date
     * @param Carbon\Carbon $end_date
     * @return Illuminate\Support\Collection
     */
    public function getOrdersFromApi(Carbon $start_date = null, Carbon $end_date = null): Collection
    {
        $response = $this->woocommerce->request(
            "GET",
            "/wp-json/wc/v3/orders",
            query_params: [
                "after" => $start_date->toIso8601String(),
                "before" => $end_date->toIso8601String()
            ]
        );
        if ($response->status() != 200)
            throw new OrderApiErrorException($response->body(), $response->status());
        $response["abc"];
        return $response->collect();
    }

    public function syncOrdersFromApi(Collection $orders): array
    {
        $data = $this->order_service->formatOrdersForSync($orders);
        DB::transaction(function () use ($data) {
            LineItem::upsert(
                $data["line_items"],
                ["id"]
            );
            Order::upsert(
                $data["orders"],
                ["id"]
            );
        });
        return $data;
    }

    public function getAllOrders(array $relations= [], array $filter = []): LengthAwarePaginator
    {
        $orders = Order::with($relations)
            ->when($filter["search_query"] ?? false, function (Builder $query) use ($filter) {
                $query->whereAny(
                    ["number", "order_key"],
                    "LIKE",
                    "%{$filter['search_query']}%"
                );
            })
            ->when($filter["start_date"] ?? false, function (Builder $query) use($filter) {
                $query->whereDate("date_created", ">=", $filter["start_date"]);
            })
            ->when($filter["end_date"] ?? false, function (Builder $query) use($filter) {
                $query->whereDate("date_created", "<=", $filter["end_date"]);
            })
            ->when($filter["status"] ?? false, function (Builder $query) use ($filter) {
                $query->where("status", $filter["status"]);
            })
            ->orderBy("date_created", ($filter["sort_order"] ?? "DESC"))
            ->paginate($filter["limit"] ?? 15);
        return $orders;
    }

    public function removeUnmodifiedOrders(int $days_to_go_back): int
    {
        $affected_rows = Order::where("updated_at", "<", now()->subDays($days_to_go_back))->delete();
        return $affected_rows;
    }
}
