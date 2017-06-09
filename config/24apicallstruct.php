<?php

return [
    "news"=>[
        "create"=>[
            'token' => '',
            'status' => 'inactive',
            'authors' => [],
            'crop_detail' => [
                'image_id' => 0,
                'width' => 0,
                'height' => 0,
                'x_coord' => 0,
                'y_coord' => 0,
                'type' => 'detail_crop',
                'id' => 0,
                'src' => '',
                ],
            'title' => '',
            'advert' => '',
            'text' => '',
            'remove_after_36' => false,
            'last_edit_by' => 'API call',
            'created_by' => 0,
            'images' => [
                [
                    'id' => 0,
                    'src' => '',
                    'main' => false,
                    "author" => [
                        "name" => ""
                        ],
                    "copyright" => [
                        "link" => "",
                        "origin" => ""
                    ]
                ]
                ],
            'tags_super' => [
                [
                    'id' => 0,
                    'title' => '',
                    'type' => 0,
                    'priority' => 1,
                    'translateTitle' => ''
                ]
                ],
            'tags_geo' => [],
            'tags_service' => [],
            'tags_promo' => [],
            'tags_program' => [],
            'tags_simple' => [],
            'video' => [
                [
                    'src' => '',
                    'url' => '',
                    'cdn_video_id' => '',
                    'cdn_player_id' => ''
                ]
                ]
        ]
    ]
];
