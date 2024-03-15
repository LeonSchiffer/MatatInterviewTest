<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetOrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "status" => ["nullable", Rule::in([Order::STATUS_COMPLETED, Order::STATUS_PENDING, Order::STATUS_PROCESSING])],
            "limit" => ["nullable", "integer"],
            "sort_order" => ["nullable", Rule::in(["ASC", "DESC"])],
            "search_query" => ["nullable"],
            "start_date" => ["nullable", "date"],
            "end_date" => ["nullable", "date"],
        ];
    }

    public function messages(): array
    {
        return [
            "status.in" => "Status can only be " . implode(", ", [Order::STATUS_COMPLETED, Order::STATUS_PENDING, Order::STATUS_PROCESSING]),
            "sort_order.in" => "Sort order can only be " . implode(", ", ["ASC", "DESC"])
        ];
    }
}
