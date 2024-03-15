<?php

namespace App\Console\Commands\Order;

use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Console\Command;

class RemoveUnmodifiedOrdersAfterThreeMonthsCommand extends Command
{
    public function __construct(private OrderRepositoryInterface $order)
    {
        parent::__construct();
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:remove-unmodified {days_to_go_back=90}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days_to_go_back = $this->argument("days_to_go_back");
        $this->info("Days to go back: " . $days_to_go_back);
        $affected_rows = $this->order->removeUnmodifiedOrders($days_to_go_back);
        $this->info("Orders deleted: $affected_rows");
    }
}
