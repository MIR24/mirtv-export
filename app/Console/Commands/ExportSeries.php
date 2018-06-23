<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Video;

class ExportSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '24export:series {offset: video.video_id} {limit: video.video_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launches programm series export procedure from mirtv site into mir24';

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
        $exportStatus = config("mirtv.24exportstatus");

        Video::where('export_status',$exportStatus["new"])->where('active',1)->where('archived', 0)->where('deleted',0)->chunk(200, function ($videos) {
            foreach ($videos as $oneVideo) {
                \Artisan::call('24export:oneserie',[ 'videoId' => $oneVideo->video_id]);
            }
        });
    }
}
