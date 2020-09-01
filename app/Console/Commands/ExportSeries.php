<?php

namespace App\Console\Commands;

use App\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '24export:series {broadcastId : article_broadcast.article_id} {maxLimit : integer} {--publish : Should be published after export}';

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
        $maxLimit = $this->argument("maxLimit");
        $publish = $this->option("publish");

        Log::debug("Exporting show", ["id" => $this->argument("broadcastId")]);
        Log::debug("Max rows", ["limit" => $maxLimit]);
        Log::debug("Published option got or not", ["publish" => $publish]);

        $queryBuilder = Video::where('export_status', $exportStatus["new"])
            ->where('active', 1)
            ->where('archived', 0)
            ->where('deleted', 0)
            ->where('article_broadcast_id', $this->argument("broadcastId"))
            ->orderBy('video_id', 'desc');

        $lastId = (clone $queryBuilder)
            ->skip($maxLimit)
            ->take(1)
            ->value('video_id');

        Log::debug("Last id:", ["lastId" => $lastId]);

        if ($maxLimit == 0) {
            Log::debug("No limit passed, procedure all rows");
            $this->info("No limit passed, procedure all rows");

            (clone $queryBuilder)
                ->chunk(200, function ($videos) use ($publish) {
                    foreach ($videos as $oneVideo) {
                        \Artisan::call('24export:oneserie', ['videoId' => $oneVideo->video_id, "--publish" => $publish]);
                    }
                });
        } elseif (!$lastId) {
            Log::debug("No acceptable rows found or maxlimit is bigger than available quantity");
            $this->info("No acceptable rows found or maxlimit is bigger than available quantity, stop");
        } else {
            (clone $queryBuilder)
                ->where('video_id', '>', $lastId)
                ->chunk(200, function ($videos) use ($publish) {
                    foreach ($videos as $oneVideo) {
                        \Artisan::call('24export:oneserie', ['videoId' => $oneVideo->video_id, "--publish" => $publish]);
                    }
                });
        }

    }
}
