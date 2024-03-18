## Prerequisites
- php8.2
- laravel/framework:10.47 (This version important as whereAny() query builder function is being used)
- mysql8.0
- supervisor (for running artisan schedule:work on background)

Please note that there is a Dockerfile and docker-compose.yml file that will set up all the necessary enviroment

It is recommended that you use Docker for best compatibility

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
#### Kernel.php
```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Production
        $schedule->command("order:sync-orders")->dailyAt("12:00")->timezone("Asia/Kathmandu");
        $schedule->command("order:remove-unmodified")->dailyAt("00:00")->timezone("Asia/Kathmandu");

        //Testing
        // $schedule->command("order:sync-orders")->everyMinute()->timezone("Asia/Kathmandu");
        // $schedule->command("order:remove-unmodified")->everyMinute()->timezone("Asia/Kathmandu");
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
- Uncomment the testing section if you want to run in every minute which makes it easier for testing purposes

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



