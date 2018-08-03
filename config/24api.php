<?php

return [
    "url" => [
    "image" => [
        "upload" => "https://editors17.mir24.tv/api/v1/images/upload",
        "patch" => "https://editors17.mir24.tv/api/v1/images"
        ],
    "news" => [
        "create" => "https://editors17.mir24.tv/api/v1/news/add/related",
        "update" => ""
        ],
    "episodes" => [
        "create" => "https://editors17.mir24.tv/api/v1/episodes/add/related",
        "update" => ""
        ],
    "videoplayer" => [
        "create" => "https://editors17.mir24.tv/api/v1/video/partial",
        ],
    ],
    "params" => [
        "domain" => "https://editors17.mir24.tv",
        "token" => env('24_API_TOKEN',"W7c3xa1DD3sdd2sdUAQIPijUCrOfqk"),
        "detail-image-width" => env('24_DET_W',570),
        "detail-image-height" => env('24_DET_H',422)
    ]
];
