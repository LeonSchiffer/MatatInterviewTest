<?php

namespace App\Console\Commands\Order;

use App\Exceptions\Order\OrderApiErrorException;
use App\Exceptions\Order\SyncOrderException;
use Illuminate\Console\Command;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ErrorLogService;
use Exception;

class SyncOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(private OrderRepositoryInterface $order, private ErrorLogService $log_service)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $current_date = now();
            $start_date = $current_date->copy()->subDays(30)->setTime(0, 0, 0);
            $orders = $this->order->getOrdersFromApi($start_date, $current_date);
            $this->order->syncOrdersFromApi($orders);
            $this->info("Orders synced successfully!");
        } catch(OrderApiErrorException $ex) {
            $this->log_service->logError("Api did not return response 200!", $ex->getMessage(), $ex->getCode());
        } catch(Exception $ex) {
            $this->log_service->logError($ex->getMessage(), $ex->getTraceAsString(), 500);
        }

    }
}
