## Prerequisites
- php8.2
- laravel/framework:10.47 (This version important as whereAny() query builder function is being used)
- mysql8.0
- supervisor (for running artisan schedule:work on background)

Please note that there is a Dockerfile and docker-compose.yml file that will set up all the necessary enviroment

It is recommended that you use Docker for best compatibility

## Production
- The follwing project is hosted in https://matat.grgbishal.com
- It has its **_APP_ENV_** set to **_testing_** (So the scheduler runs every minute, further more explanation in the _**Kernel.php**_ section below)
- The swagger API documentation is hosted here: https://leonschiffer.github.io/MatatInterviewTestSwagger (You can call the API directly from there)
- This project has **_deploy.yml_** in **_.github/workflows_** folder which will automatically deploy new changes from main branch to production server

## Setting up
- cp .env.example .env
- composer install
- php artisan key:generate
- php artisan migrate
  
## Env
- Please add the following variables to .env first
```bash
WOOCOMMERCE_BASE_URL="https://interview-test.matat.io"
WOOCOMMERCE_CONSUMER_KEY="ck_40d0806b16feb3bd67a4d8dbbff163c6dfcf061d"
WOOCOMMERCE_CONSUMER_SECRET="cs_9544e30809595750f8f1c6f3f9a6efcc38bfd06d"
ERROR_MAIL_ADDRESS="the email where you want to receive log files incase schedule command has an error"
```
## Using Docker
- Just run 'docker compose up' and it will set everything up
- You still have to set the env keys mentioned above though
- Also don't forget to set DB_HOST=db incase you use docker

## Understanding the architecture

#### BaseModel.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use HasFactory;

    protected $guarded = [];
}
```
- The project has a BaseModel.php
- Instead of extending Model class, all the models in the project extends this base class
- This allows me to set some properties for all the models in the project that needs to be the same
- For eg: I have set the $guarded = [], which will be applied to all the models in the project from now

#### Stubs
Using the command:
```bash
php artisan stub:publish
```
- All the stubs have been published
- And out of these stubs, mdoel.stub and request.stub has been modified to make development simpler

#### Repository Pattern
The project uses Repository Design Pattern and all the repository and interfaces have been bound in AppServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\WooCommerceApiRepositoryInterface;
use App\Repositories\OrderRepository;
use App\Repositories\WooCommerceApiRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(WooCommerceApiRepositoryInterface::class, WooCommerceApiRepository::class);
    }
}
```
#### WooCommerceApiRepository.php
```php
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

    /**
     * For making all kind of API request to external WooCommerce API
     * @param string $method values = ["GET", "POST", "PUT", "PATCH", "DELETE"]
     * @param string $url The path of the api
     * @param array $data Payload for POST/PUT/PATCH requests
     * @param array $headers To configure headers of a request
     * @param array $query_params Parameters to be attached to url
     * @return Response
     */
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
```
- This class has a request method which contains the external API request logic
- In the future, if we were to make more external API calls, we would do it from here

#### OrderRepositoryInterface.php
```php
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
```
- Interface which will be implemented by OrderRepository.php
- PHPDoc is used to document each and every function and will show up in typesense as well
- Please read the PHPDoc properly to understand what each function does

#### OrderRepository.php
```php
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
```
- This class contains all the business logic for this project
- Please refer to PHPDoc in OrderRepositoryInterface.php if you have trouble understanding what each function does

#### Kernel.php
```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (App::environment("testing")) {
            //Testing

            // Ignore this command. This is here just for testing purpose
            $schedule->command("inspire")->everyMinute()->appendOutputTo(storage_path("logs/inspire.log"));

            $schedule->command("order:sync-orders")->everyMinute()->timezone("Asia/Kathmandu");
            $schedule->command("order:remove-unmodified")->everyMinute()->timezone("Asia/Kathmandu");
        } else {
            // Production
            $schedule->command("order:sync-orders")->dailyAt("12:00")->timezone("Asia/Kathmandu");
            $schedule->command("order:remove-unmodified")->dailyAt("00:00")->timezone("Asia/Kathmandu");
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

```
- The Kernel.php holds the scheduler settings
- There are two scheduled commands here
- order:sync-orders is responsible for syncing orders from the external api to the local database in the span of the last 30 days
- order:remove-unmodified is responsible for removing orders that are unmodified in the last 90 days
- Timezone "Asia/Kathmandu" is being used otherwise Laravel will use UTC as the default timezone
- You can set it here or in config/app.php, its your choice
- If **_APP_ENV_** is set to **_testing_** in **_.env_** file, then the above commands will run every minute. This makes testing a lot easier
- Otherwise it will run at the specified time of the day only

#### SyncOrdersCommand.php
```php
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
```
- This class here contains the logic for order:sync-orders command
- It has two ways of handling exceptions
- OrderApiErrorException: this is when the external api request is not successful
- Exception: This will handle any other kind of exception that we wouldn't know of

#### RemoveUnmodifiedOrdersAfterThreeMonthsCommand.php
```php
public function handle()
{
    $days_to_go_back = $this->argument("days_to_go_back");
    $affected_rows = $this->order->removeUnmodifiedOrders($days_to_go_back);
    $this->info("Orders deleted: $affected_rows");
}
```
- This will check orders before 90 days that were unmodified
- It does so by using the updated_at column as a reference
- And then deletes them

#### ErrorLogService.php
```php
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
```
- This class is for logging errors that occur during running of the scheduled commands
- This will not only log error message, status code, and trace file to storage/logs/laravel.log file
- But will also send email to the address you set in ERROR_MAIL_ADDRESS in the .env file

api.php
```php
Route::get("/orders", [OrderController::class, "index"]);
```
- There is only one route in the api.php file
- This is for getting the Orders list from the local database
- The following query parameters can be used to filter the order list, all of them can be left empty
- status: The status of the order (values: completed, processing, pending)
- limit: Pagination limit per page
- sort_order: If you want to order by ascending or descending order (uses created_at column for sorting || values: ASC, DESC || default is DESC)
- search_query: Will search using the like operator on number and order_key column 
- start_date: will show result greater or equal to this date using date_created column 
- end_date: will show result lesser or equal to this date using date_created column
- View the API Swagger documentation here: https://leonschiffer.github.io/MatatInterviewTestSwagger/

#### Supervisor
```conf
[program:matat-schedule-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan schedule:work
startsecs=0
autostart=true
autorestart=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
```
- **_.docker/supervisor_** folder contains **_laravel-worker.conf_** file which has the required configuation to run **_artisan schedule:work_** command
- This will be automatically run if you use Docker
- Otherwise you will have to set this manually

#### deploy.yml
```yml
name: Deploy to Production

on:
    push:
        branches: ["main"]

jobs:
    prod-deploy:
        name: Deployment Process
        runs-on: ubuntu-latest

        steps:
            # - name: Get latest code
            #   uses: actions/checkout@v3
            - name: Deployment via SSH
              uses: appleboy/ssh-action@v1.0.3
              with:
                host: ${{ secrets.HOST }}
                username: ${{ secrets.USERNAME }}
                key: ${{ secrets.PRIVATE_KEY }}
                port: ${{ secrets.PORT }}
                script: |
                    cd /home/grgbish1/matat.grgbishal.com
                    git pull origin main
                    composer install
                    php artisan migrate --force
```
- The following deploy.yml file in .github/workflows folder will automatically push new changes in the main branch to production server




