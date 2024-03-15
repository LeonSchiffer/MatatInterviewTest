<?php

namespace App\Exceptions\Order;

use App\Mail\Order\SyncOrderErrorMail;
use App\Services\ErrorLogService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncOrderException extends Exception
{

}
