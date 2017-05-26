<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExportOneSerie extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '24export:oneserie {videoId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export particular programm serie from mirtv site into mir24';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
    }
}
