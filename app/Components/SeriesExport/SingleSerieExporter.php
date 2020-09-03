<?php


namespace App\Components\SeriesExport;


use App\Video;
use Barantaran\Platformcraft\Platform as Platform;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SingleSerieExporter
{
    /** @var ConfigDTO */
    private $config;
    /** @var boolean */
    private $dry;
    private $videoId;
    /** @var Client */
    private $client;
    private $programmConnector;

    /**
     * SingleSerieExporter constructor.
     * @param ConfigDTO $config
     * @param bool $dry
     * @param $videoId
     * @param Client $client
     * @param $programmConnector
     */
    public function __construct(ConfigDTO $config, bool $dry, $videoId, Client $client, $programmConnector)
    {
        $this->config = $config;
        $this->dry = $dry;
        $this->videoId = $videoId;
        $this->client = $client;
        $this->programmConnector = $programmConnector;
    }


    /**
     * @throws SeriesExportException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function export(): void
    {
        if ($this->dry) {
            Log::info("Dry run..");
        }

        $video = Video::find($this->videoId);
        if (!$video) {
            Log::error("Episode not found", [$video]);
            throw new SeriesExportException("Episode not found");
        }

        $this->checkExistsArticleBroadcast($video);

        if (!$this->dry) {
            $video->update(["export_status" => ExportStatusType::EXPORTING]);
        }

        $videoFilePath = $this->getVideoFilePathTODO($video);
        $videoFileName = $this->formExtension($videoFilePath);
        $imageFilePath = config("mirtv.localvideopath") . $video->video_id . "/" . $video->image;
        $platform = $this->getPlatformTODO();
        $videoPlayer = $this->vidTODO($platform, $videoFilePath, $videoFileName);
        $this->imgTODO($platform, $imageFilePath, $videoPlayer);
        /*
         * Upload image to mir24 server
         * ****************************/
        $imageUploadResult = $this->uploadImageTo24($imageFilePath, $this->config->api24token);

        $videoPlayerData = $this->videoUploadResTODO($platform, $videoPlayer);
        $playerCreated = $this->client->request('POST', $this->config->videoPlayerCreatePoint, ["json" => $videoPlayerData]);
        $playerCreatedData = json_decode($playerCreated->getBody()->getContents(), 1);
        Log::debug("Player POST result", [$playerCreatedData]);


        /*
         * Attach tags
         */
        Log::debug("Found tags", [$video->tags]);
        $newsTags = [];
        foreach ($video->tags as $oneTag) {
            $newsTags[] = ["title" => $oneTag->name];
        }
        $newsData = $this->getNewsData($video, $imageUploadResult, $playerCreatedData, $newsTags);
        $this->publishTODO($newsData, $imageFilePath, $video);
    }

    /**
     * @param $video
     * @throws SeriesExportException
     */
    private function checkExistsArticleBroadcast($video)
    {
        /*
         *  Trying to attach programm tag
         * ******************************/
        if (array_key_exists($video->article_broadcast_id, $this->programmConnector)) {
            $this->info("Attach programm tag for " . $this->programmConnector[$video->article_broadcast_id]["title"] . " broadcast");
            Log::info("Attach programm tag for " . $this->programmConnector[$video->article_broadcast_id]["title"] . " broadcast");
        } else {
            Log::error("Can't find broadcast connection for article_broadcast " . $video->article_broadcast_id);
            throw new SeriesExportException("Can't find broadcast connection for article_broadcast " . $video->article_broadcast_id);
        }
    }

    private function getVideoFilePathTODO($video)
    {
        if ($video->main_base_id > 0) {
            Log::info("Video file is hosted on remote server", [$video]);
            $videoFilePath = config("platformcraft.24videopath") . $video->video;
        } else {
            Log::info("Video file is hosted on local server", [$video]);
            $videoFilePath = config("platformcraft.localvideopath") . $video->video_id . "/" . $video->video;
        }
        return $videoFilePath;
    }

    private function formExtension($filename, $ext = "mp4"): string
    {
        echo "Passed filepath $filename\n";
        $file_parts = pathinfo($filename);

        if (empty($file_parts["extension"])) {
            return basename($filename) . "." . $ext;
        } else {
            return basename($filename);
        }
    }

    /**
     * @return Platform
     * @throws SeriesExportException
     */
    private function getPlatformTODO()
    {
        $platform = new Platform(
            config("platformcraft.apiuserid"),
            config("platformcraft.hmackey")
        );

        if (!$platform) {
            Log::error("Can't start platformcraft API", $platform);
            throw new SeriesExportException("Can't start platformcraft API");
        }

        return $platform;
    }

    private function vidTODO($platform, $videoFilePath, $videoFileName)
    {
        /*
         * Establish video player at Platformcraft CDN
         * *******************************************/
        $videoPlayer = null;
        if (!$this->dry) {
            $videoPlayer = $platform->setupVideoPlayer(["auto" => ["path" => $videoFilePath, "name" => $videoFileName]])["response"];
        }

        if (!$this->dry && !$videoPlayer) {
            Log::error("Can't setup videoplayer", [$videoPlayer, $platform->getMyError()]);
            exit;
        }

        $this->info("Setup videoplayer..");
        if (!$this->dry) {
            Log::debug("Setup videoplayer..", $videoPlayer);
        }

        return $videoPlayer;
    }

    /**
     * @param Platform $platform
     * @param $imageFilePath
     * @param $videoPlayer
     * @throws SeriesExportException
     */
    private function imgTODO(Platform $platform, $imageFilePath, $videoPlayer)
    {
        /*
         * Attach image to player
         * ***********************/
        $image = null;
        if (!$this->dry) {
            $image = $platform->attachImageToPlayer($imageFilePath, $videoPlayer["player"]["id"]);
        }

        if (!$this->dry && !$image) {
            Log::error("Can't attach image to videoplayer");
//            Log::error("Last platform response", [$platform->getMyError()]); # TODO protected
            throw new SeriesExportException("Can't attach image to videoplayer");
        }
        $this->info("Setup screenshot..");
        if (!$this->dry) {
            Log::debug("Setup screenshot..", $image);
        }
    }

    /**
     * @param $imageFilePath
     * @param $apiToken
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function uploadImageTo24($imageFilePath, $apiToken)
    {
        /*
 * Upload image to mir24 server
 * ****************************/

        $imageData = [
            'multipart' =>
                [
                    [
                        'name' => 'token',
                        'contents' => $apiToken
                    ],
                    [
                        'name' => 'autocrop',
                        'contents' => 1
                    ],
                    [
                        'name' => 'original',
                        'contents' => fopen($imageFilePath, 'r')
                    ],
                ]
        ];

        $this->info("Goin to post image to mir24..");
        Log::debug("Goin to post image to mir24..", ["file" => $imageFilePath, "data" => $imageData]);

        $imageUploaded2Mir = $this->client->request('POST', config("24api.url.image.upload"), $imageData);
        $imageUploadResult = json_decode($imageUploaded2Mir->getBody()->getContents(), 1);

        Log::debug("Posted image to mir24..", $imageUploadResult);

        return $imageUploadResult;
    }

    private function videoUploadResTODO($platform, $videoPlayer)
    {
        /*
 * Post videoplayer to mir24
 * */
        $videoUploadRes = $platform->getVideoUploadedRes()[0];
        Log::debug("Platformcraft uploaded video data", [$videoUploadRes]);
        $videoPlayerData["video_id"] = $videoUploadRes['response']['object']['id'];
        $videoPlayerData["id"] = $videoPlayer["player"]["id"];
        $videoPlayerData["frame_tag"] = $videoPlayer["player"]["frame_tag"];
        $videoPlayerData["token"] = $this->config->api24token;
        Log::debug("POSTing player..", [$videoPlayerData]);
        return $videoPlayerData;
    }

    private function getNewsData($video, $imageUploadResult, $playerCreatedData, $newsTags)
    {
        /*
 * Post publication to mir24
 * ****************************/
        $newsData = config("24apicallstruct.news.create");

        if ($this->config->publish) {
            $newsData["status"] = "active";
        } else {
            $newsData["status"] = "inactive";
        }

        $newsData["token"] = $this->config->api24token;
        $newsData["title"] = $video->title;
        $newsData["advert"] = $video->description;
        $newsData["text"] = $video->text;
        $newsData["created_at"] = $video->created_at;
        $newsData["published_at"] = $video->start;
        $newsData["teleshow_airtime"] = $video->start;
        $tagProgramData[] = array("id" => $this->programmConnector[$video->article_broadcast_id]["programTagId"]);
        $tagChannelData[] = array("id" => $this->programmConnector[$video->article_broadcast_id]["channelTagId"]);
        $newsData["tags_program"] = $tagProgramData;
        $newsData["tags_channel"] = $tagChannelData;
        $newsData["seo_title"] = $video->title;
        if (!empty($newsTags)) {
            $newsData["tags_simple"] = $newsTags;
        }
        $newsData["tags_adjective"][] = array("id" => config("mirtv.adjectiveTagId"));
        $newsData["tags_super"][] = array("id" => config("mirtv.superTagId"));
        $newsData["age_restriction"] = $video->age_restriction;

        if (!$this->dry) {
            $newsData["video"][0]["id"] = $playerCreatedData["id"];
            $newsData["images"][0] = $imageUploadResult;
            $newsData["images"][0]["copyright"]["link"] = $video->copyright_link;
            $newsData["images"][0]["copyright"]["origin"] = $video->copyright_text;
            $newsData["images"][0]["alt"] = $video->title;
            $newsData["images"][0]["title"] = $video->title;
        }

        return $newsData;
    }

    /**
     * @param $newsData
     * @param $imageFilePath
     * @param $video
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function publishTODO($newsData, $imageFilePath, $video)
    {
        $messageCreate = "Creating news..";
        $this->info($messageCreate);
        Log::info($messageCreate);
        Log::debug($messageCreate, ["endpoint" => $this->config->newsCreatePoint, "payload" => $newsData]);
        if (!$this->dry) {
            $newsCreated = $this->client->request('POST', $this->config->newsCreatePoint, ["json" => $newsData]);
            $newsCreateResult = json_decode($newsCreated->getBody()->getContents(), 1);
            $newsCreateResult["mirtv_id"] = $video->video_id;
            Log::debug("News created..", $newsCreateResult);

            $tagPremiumChannelData[] = array("id" => config("mirtv")["premiumChannelTagId"]);
            $premiumFlag = $this->programmConnector[$video->article_broadcast_id]["cloneIntoPremium"];
            if ($premiumFlag) {
                $imageUploadResult = $this->uploadImageTo24($imageFilePath, $this->config->api24token);
                $newsData["images"][0] = $imageUploadResult;

                $newsData["tags_channel"] = $tagPremiumChannelData;
                $newsData["status"] = "inactive";
                $newsCreated = $this->client->request('POST', $this->config->newsCreatePoint, ["json" => $newsData]);
                $newsCreateResult = json_decode($newsCreated->getBody()->getContents(), 1);
                $newsCreateResult["mirtv_id"] = $video->video_id;
                Log::debug("Premium news created..", $newsCreateResult);
            }
            $video->update(["export_status" => ExportStatusType::DONE]);
        }
    }

    private function info($message)
    {
        # TODO use console output
    }
}
