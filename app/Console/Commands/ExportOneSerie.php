<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\ExportImage;
use App\Video;
use App\Tag;
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
    protected $signature = '24export:oneserie {videoId : video.video_id} {--dry : Only log available data, do not perform actual export} {--publish : Publish exported immediately}';

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
        $publish = $this->option("publish");
        Log::debug("Should be published:", ["publish"=>$publish]);
        $api24token = config("24api.params.token");
        $newsCreatePoint = config("24api.url.episodes.create");
        $videoPlayerCreatePoint = config("24api.url.videoplayer.create");
        $video = Video::find($this->argument("videoId"));
        $exportStatus = config("mirtv.24exportstatus");
        $client = new Client();

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
            $tagProgramData[] = Array("id" => config("mirtv.24programm_connector")[$video->article_broadcast_id]["programTagId"]);
            $tagChannelData[] = Array("id" => config("mirtv.24programm_connector")[$video->article_broadcast_id]["channelTagId"]);
            $adjectiveTagData = Array("id" => config("mirtv.adjectiveTagId"));
            $superTagData = Array("id" => config("mirtv.superTagId"));
            $tagPremiumChannelData[] = Array("id" => config("mirtv")["premiumChannelTagId"]);
            $premiumFlag = config("mirtv.24programm_connector")[$video->article_broadcast_id]["cloneIntoPremium"];
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
        } else {/*{*/
            Log::info("Video file is hosted on local server", [$video]);/*}*/
            $videoFilePath = config("platformcraft.localvideopath") . $video->video_id . "/" . $video->video;
        }
        $videoFileName = $this->formExtension($videoFilePath);

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
        if(!$dry) $videoPlayer = $platform->setupVideoPlayer(["auto"=>["path"=>$videoFilePath,"name"=>$videoFileName]])["response"];

        if(!$dry && !$videoPlayer) {
            Log::error("Can't setup videoplayer", [$videoPlayer ,$platform->getMyError()]);
            exit;
        }

        $this->info("Setup videoplayer..");
        if(!$dry) Log::debug("Setup videoplayer..", $videoPlayer);

        /*
         * Attach image to player
         * ***********************/
        if(!$dry) $image = $platform->attachImageToPlayer($imageFilePath, $videoPlayer["player"]["id"]);

        if(!$dry && !$image) {
            Log::error("Can't attach image to videoplayer", [$result,$platform->getMyError()]);
            Log::error("Last platform response", [$platform->getMyError()]);
            exit;
        }
        $this->info("Setup screenshot..");
        if(!$dry) Log::debug("Setup screenshot..", $image);

        /*
         * Upload image to mir24 server
         * ****************************/
        $imageUploadResult = $this->uploadImageTo24($imageFilePath, $api24token);

        /*
         * Post videoplayer to mir24
         * */
        $videoUploadRes = $platform->getVideoUploadedRes()[0];
        Log::debug("Platformcraft uploaded video data", [$videoUploadRes]);
        $videoPlayerData["video_id"] = $videoUploadRes['response']['object']['id'];
        $videoPlayerData["id"] = $videoPlayer["player"]["id"];
        $videoPlayerData["frame_tag"] = $videoPlayer["player"]["frame_tag"];
        $videoPlayerData["token"] = $api24token;
        Log::debug("POSTing player..", [$videoPlayerData]);
        $playerCreated = $client->request('POST', $videoPlayerCreatePoint, ["json"=>$videoPlayerData]);
        $playerCreatedData = json_decode($playerCreated->getBody()->getContents(),1);
        Log::debug("Player POST result", [$playerCreatedData]);

        /*
         * Attach tags
         */

        Log::debug("Found tags",[$video->tags]);
        foreach($video->tags as $oneTag) {
            $newsTags[] = [ "title" => $oneTag->name ];
        }

        /*
         * Post publication to mir24
         * ****************************/
        $newsData = config("24apicallstruct.news.create");

        if($publish) $newsData["status"] = "active";
        else $newsData["status"] = "inactive";

        $newsData["token"] = $api24token;
        $newsData["title"] = $video->title;
        $newsData["advert"] = $video->description;
        $newsData["text"] = $video->text;
        $newsData["created_at"] = $video->created_at;
        $newsData["published_at"] = $video->start;
        $newsData["teleshow_airtime"] = $video->start;
        $newsData["tags_program"] = $tagProgramData;
        $newsData["tags_channel"] = $tagChannelData;
        $newsData["seo_title"] = $video->title;
        if(!empty($newsTags))$newsData["tags_simple"] = $newsTags;
        $newsData["tags_adjective"][] = $adjectiveTagData;
        $newsData["tags_super"][] = $superTagData;
        $newsData["age_restriction"] = $video->age_restriction;

        if(!$dry) {
            $newsData["video"][0]["id"] = $playerCreatedData["id"];
            $newsData["images"][0] = $imageUploadResult;
            $newsData["images"][0]["copyright"]["link"] = $video->copyright_link;
            $newsData["images"][0]["copyright"]["origin"] = $video->copyright_text;
            $newsData["images"][0]["alt"] = $video->title;
            $newsData["images"][0]["title"] = $video->title;
        }

        $this->info("Creating news..");
        Log::info("Creating news..");
        Log::debug("Creating news..", ["endpoint" => $newsCreatePoint, "payload" => $newsData]);
        if(!$dry) {
            $newsCreated = $client->request('POST', $newsCreatePoint, ["json"=>$newsData]);
            $newsCreateResult = json_decode($newsCreated->getBody()->getContents(),1);
            Log::debug("News created..", $newsCreateResult);

            if($premiumFlag){
                $imageUploadResult = $this->uploadImageTo24($imageFilePath, $api24token);
                $newsData["images"][0] = $imageUploadResult;

                $newsData["tags_channel"] = $tagPremiumChannelData;
                $newsData["status"] = "inactive";
                $newsCreated = $client->request('POST', $newsCreatePoint, ["json"=>$newsData]);
                $newsCreateResult = json_decode($newsCreated->getBody()->getContents(),1);
                Log::debug("Premium news created..", $newsCreateResult);
            }
            $video->update(["export_status" => $exportStatus["done"]]);
        }
        $this->info("Done.");
    }

    private function formExtension($filename, $ext="mp4")
    {
        echo "Passed filepath $filename\n";
        $file_parts = pathinfo($filename);

        if(empty($file_parts["extension"]))
        {
            $filenameFormed = basename($filename).".".$ext;
            return $filenameFormed;
        } else
        {
            return basename($filename);
        }
    }

    /*
     * Upload image to mir24 server
     * ****************************/
    private function uploadImageTo24($imageFilePath, $apiToken)
    {
        $client = new Client();

        $imageData = [
            'multipart' =>
            [
                [
                    'name'     => 'token',
                    'contents' => $apiToken
                ],
                [
                    'name'     => 'autocrop',
                    'contents' => 1
                ],
                [
                    'name'     => 'original',
                    'contents' => fopen($imageFilePath, 'r')
                ],
            ]
        ];

        $this->info("Goin to post image to mir24..");
        Log::debug("Goin to post image to mir24..", ["file"=>$imageFilePath, "data"=>$imageData]);

        $imageUploaded2Mir = $client->request('POST', config("24api.url.image.upload"), $imageData);
        $imageUploadResult = json_decode($imageUploaded2Mir->getBody()->getContents(),1);

        Log::debug("Posted image to mir24..", $imageUploadResult);

        return $imageUploadResult;
    }
}
