<?php

namespace App\Console\Commands;

use App\Components\SeriesExport\ConfigDTO;
use App\Components\SeriesExport\SeriesExportException;
use App\Components\SeriesExport\SingleSerieExporter;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportOneSerie extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '24export:oneserie {videoId : video.video_id} {--dry : Only log available data, do not perform actual export} {--publish : Publish exported immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export particular programm serie from mirtv site into mir24';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            /*
             * Setup export procedure
             * ***********************/
            $this->info("Launch One Serie export..");
            Log::debug("Launch One Serie export");
            Log::info("Exporting Serie", ["id" => $this->argument("videoId")]);

            $dry = $this->option("dry");
            $config = new ConfigDTO(
                $this->option("publish"),
                config("24api.params.token"),
                config("24api.url.episodes.create"),
                config("24api.url.videoplayer.create")
            );
            Log::debug("Should be published:", ["publish" => $config->publish]);

            $client = new Client();
            $programmConnector = config("mirtv.24programm_connector");

            $exporter = new SingleSerieExporter($config, $dry, $this->argument("videoId"), $client, $programmConnector);
            $exporter->export();

            $this->info("Done.");
        } catch (SeriesExportException $exception) {
            $this->error($exception->getMessage());
        }
    }
}
