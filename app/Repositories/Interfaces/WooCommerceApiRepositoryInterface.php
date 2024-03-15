<?php

namespace App\Repositories\Interfaces;

use Illuminate\Http\Client\Response;

interface WooCommerceApiRepositoryInterface
{
    public function request(string $method, string $url, array $data = [], array $headers = [], array $query_params = []): Response;
}
