<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        echo "Redirecting to order api in 5 seconds...";
        sleep(5);
        return redirect()->to(route("api.order.index"));
    }
}
