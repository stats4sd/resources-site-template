<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FreshWithExampleData extends Command
{
    protected $signature = 'app:fresh';

    protected $description = 'Run migrate:fresh --seed then seed example troves/collections';

    public function handle()
    {
        $this->call('migrate:fresh', ['--seed' => true]);
        $this->call('db:seed', ['--class' => 'Database\Seeders\Example\ExampleDataSeeder']);
    }
}
