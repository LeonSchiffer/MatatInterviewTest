<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\Order\SyncOrderErrorMail;
use Exception;

class ErrorLogService
{
    public function logError(string $message, string $log_trace, int $status_code)
    {
        Log::info($message);
        Log::info("Status: $status_code");
        Log::info($log_trace);
        Mail::to(config("app.error_mail"))->send(new SyncOrderErrorMail($message, $log_trace, $status_code));
    }
}
