<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetDbAndConvert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rdbc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the database and bring in all the needed data from the old database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // reset the database
        $this->call('migrate:fresh');

        // seed the database
        $this->call('db:seed');

        // convert old troves to new troves
        $this->call('app:convert-old-to-new-troves');

        // convert old collections to new collections
        $this->call('app:convert-old-to-new-collections');
    }
}
