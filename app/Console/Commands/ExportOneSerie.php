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
    protected $signature = '24export:oneserie {videoId : video.video_id} {--dry : Only log available data, do not perform actual export}';

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
        /*
         * Setup export procedure
         * ***********************/
        $this->info("Launch One Serie export..");
        Log::debug("Launch One Serie export");
        Log::info("Exporting Serie",["id"=>$this->argument("videoId")]);

        $dry = $this->option("dry");
        $api24token = config("24api.params.token");
        $newsCreatePoint = config("24api.url.episodes.create");
        $video = Video::find($this->argument("videoId"));
        $exportStatus = config("mirtv.24exportstatus");

        if($dry) Log::info("Dry run..");

        if(!$video) {
            Log::error("Episode not found", [$video]);
            return;
        }

        /*
         *  Trying to attach programm tag
         * ******************************/
        $programmConnector = config("mirtv.24programm_connector");
        if(array_key_exists($video->article_broadcast_id, $programmConnector)){
            $tagProgramData[] = Array("id" => config("mirtv.24programm_connector")[$video->article_broadcast_id]["24tagid"]);
            $this->info("Attach programm tag for " . config("mirtv.24programm_connector")[$video->article_broadcast_id]["title"] . " broadcast");
            Log::info("Attach programm tag for " . config("mirtv.24programm_connector")[$video->article_broadcast_id]["title"] . " broadcast");
        } else {
            $this->error("Can't find broadcast connection for article_broadcast " . $video->article_broadcast_id);
            Log::error("Can't find broadcast connection for article_broadcast " . $video->article_broadcast_id);
            return;
        }

        if(!$dry) $video->update(["export_status"=>$exportStatus["exporting"]]);

        if($video->main_base_id > 0){
            Log::info("Video file is hosted on remote server", [$video]);
            $videoFilePath = config("platformcraft.24videopath") . $video->video;
        } else {
            Log::info("Video file is hosted on local server", [$video]);
            $videoFilePath = config("platformcraft.localvideopath") . $video->video_id . "/" . $video->video;
        }
        $imageFilePath = config("mirtv.localvideopath") . $video->video_id . "/" . $video->image;

        $platform = new Platform(
            config("platformcraft.apiuserid"),
            config("platformcraft.hmackey")
        );

        if(!$platform) {
            Log::error("Can't start platformcraft API", $platform);
            exit;
        }


        /*
         * Establish video player at Platformcraft CDN
         * *******************************************/
        if(!$dry) $videoPlayer = $platform->setupVideoPlayer($videoFilePath);

        if(!$dry && !$videoPlayer) {
            Log::error("Can't setup videoplayer", [$result,$platform::getMyError()]);
            exit;
        }

        $this->info("Setup videoplayer..");
        if(!$dry) Log::debug("Setup videoplayer..", $videoPlayer);


        /*
         * Attach image to player
         * ***********************/
        if(!$dry) $image = $platform->attachImageToPlayer($imageFilePath, $videoPlayer["player"]["id"]);

        if(!$dry && !$image) {
            Log::error("Can't attach image to videoplayer", [$result,$platform::getMyError()]);
            exit;
        }
        $this->info("Setup screenshot..");
        if(!$dry) Log::debug("Setup screenshot..", $image);


        /*
         * Upload image to mir24 server
         * ****************************/
        $client = new Client();

        $imageData = [
            'multipart' =>
            [
                [
                    'name'     => 'token',
                    'contents' => $api24token
                ],
                [
                    'name'     => 'original',
                    'contents' => fopen($imageFilePath, 'r')
                ],
            ]
        ];
        $this->info("Goin to post image to mir24..");
        Log::debug("Goin to post image to mir24..", ["file"=>$imageFilePath, "data"=>$imageData]);
        if(!$dry) {
            $imageUploaded2Mir = $client->request('POST', config("24api.url.image.upload"), $imageData);
            $imageUploadResult = json_decode($imageUploaded2Mir->getBody()->getContents(),1);
            Log::debug("Posted image to mir24..", $imageUploadResult);
        }


        /*
         * Mark crop coordinates
         * **********************/
        $crop = array (
            'detail_crop' =>
            array (
                'xCoord' => 0,
                'yCoord' => 0,
                'width' => config("24api.params.detail-image-width"),
                'height' => config("24api.params.detail-image-height"),
            ),
            'token' => $api24token
        );
        if(!$dry) $patchUrl = config("24api.url.image.patch")."/".$imageUploadResult["id"];
        else $patchUrl = config("24api.url.image.patch")."/"."1234567890";

        $this->info("Patching image with crop params..");
        Log::debug("Patching image with crop params..", ["patch-url"=>$patchUrl]);
        if(!$dry) {
            $cropMarked = $client->request('PATCH', $patchUrl, ["json"=>$crop]);
            $cropMarkedResult = json_decode($cropMarked->getBody()->getContents(),1);
            Log::debug("Mark detail crop", $cropMarkedResult);
        }


        /*
         * Post publication to mir24
         * ****************************/
        $newsData = config("24apicallstruct.news.create");

        $newsData["token"] = $api24token;
        $newsData["title"] = $video->title;
        $newsData["advert"] = $video->description;
        $newsData["text"] = $video->text;

        $newsData["tags_program"] = $tagProgramData;

        if(!$dry) {
            $imageMeta["id"] = $imageUploadResult["id"];
            $imageMeta["src"] = $imageUploadResult["src"];
            $imageMeta["remove_after"] = 0;
            $newsData["images"][0] = $imageMeta;
            $newsData["crop_detail"]["id"] = $cropMarkedResult[0]["id"];
        }

        $this->info("Creating news..");
        Log::info("Creating news..");
        Log::debug("Creating news..", ["endpoint" => $newsCreatePoint, "payload" => $newsData]);
        if(!$dry) {
            $newsCreated = $client->request('POST', $newsCreatePoint, ["json"=>$newsData]);
            $newsCreateResult = json_decode($newsCreated->getBody()->getContents(),1);
            Log::debug("News created..", $newsCreateResult);

            $video->update(["export_status" => $exportStatus["done"]]);
        }
        $this->info("Done.");
    }
}
