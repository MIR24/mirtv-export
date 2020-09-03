<?php


namespace App\Components\SeriesExport;


class ConfigDTO
{
    public $publish;
    public $api24token;
    public $newsCreatePoint;
    public $videoPlayerCreatePoint;

    /**
     * ConfigDTO constructor.
     * @param $publish
     * @param $api24token
     * @param $newsCreatePoint
     * @param $videoPlayerCreatePoint
     */
    public function __construct($publish, $api24token, $newsCreatePoint, $videoPlayerCreatePoint)
    {
        $this->publish = $publish;
        $this->api24token = $api24token;
        $this->newsCreatePoint = $newsCreatePoint;
        $this->videoPlayerCreatePoint = $videoPlayerCreatePoint;
    }
}
