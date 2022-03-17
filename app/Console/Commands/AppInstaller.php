<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AppInstaller extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install
                            {--db-host=localhost : Database Host}
                            {--db-port=3306 : Port for the database}
                            {--db-database= : Name for the database}
                            {--db-username=root : Username for accessing the database}
                            {--db-password= : Password for accessing the database, it can be blank}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To install the application via CLI';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->missingRequiredOptions()) {
            $this->error('Missing required options');
            $this->line('please run');
            $this->line('php artisan app:install --help');
            $this->line('to see the command usage.');
            return 0;
        }

        $this->alert('Application is installing...');

        static::copyEnvExampleToEnv();
        $this->generateAppKey();
        $this->updateEnvVariablesFromOptions();

        $this->info('Env file created successfully.');

        $this->info('Runnning migrations and seeders...');

        if (!static::runMigrationsWithSeeders()) {
            $this->error('Your database credentials are wrong!');
            return 0;
        }

        $this->alert('Application is installed successfully.');
        return 1;
    }


    public function missingRequiredOptions()
    {
        return !$this->option('db-database');
    }

    public static function copyEnvExampleToEnv()
    {

        if (!is_file(base_path('.env')) && is_file(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
        }
    }

    public static function generateAppKey()
    {
        Artisan::call('key:generate');
    }

    public function updateEnvVariablesFromOptions()
    {
        $this->updateEnv([
            'DB_HOST' => $this->option('db-host'),
            'DB_PORT' => $this->option('db-port'),
            'DB_DATABASE' => $this->option('db-database'),
            'DB_USERNAME' => $this->option('db-username'),
            'DB_PASSWORD' => $this->option('db-password'),
        ]);

        $conn = config('database.default', 'mysql');

        $dbConfig = Config::get("database.connections.$conn");

        $dbConfig['host'] = $this->option('db-host');
        $dbConfig['port'] = $this->option('db-port');
        $dbConfig['database'] = $this->option('db-database');
        $dbConfig['username'] = $this->option('db-username');
        $dbConfig['password'] = $this->option('db-password');

        Config::set("database.connections.$conn", $dbConfig);

        DB::purge($conn);
        DB::reconnect($conn);
    }

    public static function updateEnv($data)
    {
        $env = file_get_contents(base_path('.env'));

        $env = explode("\n", $env);

        foreach ($data as $dataKey => $dataValue) {
            $alreadyExistInEnv = false;

            foreach ($env as $envKey => $envValue) {
                $entry = explode('=', $envValue, 2);

                // Check if exists or not in env file
                if ($entry[0] == $dataKey) {
                    $env[$envKey] = $dataKey . '=' . $dataValue;
                    $alreadyExistInEnv = true;
                } else {
                    $env[$envKey] = $envValue;
                }
            }

            // add the variable if not exists in env
            if (!$alreadyExistInEnv) {
                $env[] = $dataKey . '=' . $dataValue;
            }
        }

        $env = implode("\n", $env);

        file_put_contents(base_path('.env'), $env);

        return true;
    }

    public static function runMigrationsWithSeeders()
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
