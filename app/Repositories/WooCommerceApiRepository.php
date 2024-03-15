<?php

namespace App\Repositories;

use App\Repositories\Interfaces\WooCommerceApiRepositoryInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WooCommerceApiRepository implements WooCommerceApiRepositoryInterface
{
    private string $base_url;
    private string $consumer_key;
    private string $consumer_secret;

    public function __construct()
    {
        $this->base_url = config("app.woocommerce_base_url");
        $this->consumer_key = config("app.woocommerce_consumer_key");
        $this->consumer_secret = config("app.woocommerce_consumer_secret");
    }

    public function request(string $method, string $path, array $from_data = [], array $headers = [], array $query_params = []): Response
    {
        $response = Http::withQueryParameters([
            ...$query_params,
            "consumer_key" => $this->consumer_key,
            "consumer_secret" => $this->consumer_secret
        ])
            ->send($method, "$this->base_url/$path", [
                "headers" => $headers,
                "form_params" => $from_data
            ]);
        return $response;
    }
}
