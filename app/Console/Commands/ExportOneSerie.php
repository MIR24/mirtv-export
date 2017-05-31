<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\ExportImage;
use App\Video;
use \Barantaran\Platformcraft\Platform as Platform;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;

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
        /******************
         * Setup export procedure
         * ***********************/
        Log::debug("Launch One Serie export");
        Log::info("Exporting Serie",["id"=>$this->argument("videoId")]);

        $video = Video::find($this->argument("videoId"));

        $videoFilePath = config("platformcraft.localvideopath") . $video->video_id . "/" . $video->video;
        $imageFilePath = config("platformcraft.localvideopath") . $video->video_id . "/" . $video->image;

        $platform = new Platform(
            config("platformcraft.apiuserid"),
            config("platformcraft.hmackey")
        );

        if(!$platform) {
            Log::error("Can't start platformcraft API", $platform);
            exit;
        }


        /***********************
         * Establish video player at Platformcraft CDN
         * *******************************************/
        $videoPlayer = $platform->setupVideoPlayer($videoFilePath);

        if(!$videoPlayer) {
            Log::error("Can't setup videoplayer", [$result,$platform::getMyError()]);
            exit;
        }

        Log::debug("Setup videoplayer..", $videoPlayer);


        /***********************
         * Attach image to player
         * ***********************/
        $image = $platform->attachImageToPlayer($imageFilePath, $videoPlayer["player"]["id"]);

        if(!$image) {
            Log::error("Can't attach image to videoplayer", [$result,$platform::getMyError()]);
            exit;
        }
        Log::debug("Setup screenshot", $image);


        /********************
         * Upload image to mir24 server
         * ****************************/
        $client = new Client();

        $imageUploaded2Mir = $client->request('POST', 'https://editors3.mir24.tv/api/v1/images/upload', [
                'multipart' =>
                [
                    [
                        'name'     => 'token',
                        'contents' => 'W7c3xa1DD3sdd2sdUAQIPijUCrOfqk'
                    ],
                    [
                        'name'     => 'original',
                        'contents' => fopen($imageFilePath, 'r')
                    ],
                ]
            ]
        );

        Log::debug("Post image to mir24..", [$imageUploaded2Mir->getBody()->getContents()]);


        /****************
         * Post publication to mir24 DB
         * ****************************/

        exit;
        dispatch(new ExportImage($video));
    }
}
