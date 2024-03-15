<?php

namespace App\Repositories\Interfaces;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    /**
     * Get order list from the external WooCommerce API
     * @param Carbon $start_date Filtering the minimum order date to retrieve
     * @param Carbon $start_date Filtering the maximum order date to retrieve
     * @return Collection
     */
    public function getOrdersFromApi(Carbon $start_date = null, Carbon $end_date = null): Collection;

    /**
     * Upserts the orders from the external API to our local database
     * @param Collection $orders The list of orders to upsert
     * @return array The list of formatted orders that were upserted
     */
    public function syncOrdersFromApi(Collection $orders): array;

    /**
     * Get order list from our local database
     * @param array $relations The list of relations you want to eager load
     * @param array $filter The options for filtering the orders list
     * @param LengthAwarePaginator The paginated data to return
     */
    public function getAllOrders(array $relations, array $filter = []): LengthAwarePaginator;

    /**
     * This deletes the orders that were unmodified for the last x days using the updated_at column
     * @param int $days_to_go_back The days to go back for the where filter query
     * @return int Returns the number of rows deleted
     */
    public function removeUnmodifiedOrders(int $days_to_go_back): int;
}
