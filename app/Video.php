<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $video_id
 * @property integer $article_broadcast_id
 * @property string $title
 * @property string $description
 * @property string $text
 * @property string $image
 * @property string $image_second
 * @property string $image_third
 * @property string $image_fourth
 * @property integer $image_counter
 * @property string $video
 * @property integer $age_restriction
 * @property string $copyright_text
 * @property string $copyright_link
 * @property string $created_at
 * @property integer $created_by
 * @property string $start
 * @property boolean $active
 * @property boolean $editors_choice
 * @property boolean $public_choice
 * @property boolean $new_episode
 * @property boolean $archived
 * @property boolean $deleted
 * @property integer $main_base_id
 */
class Video extends Model
{
    /**
     * @var array
     */
    protected $fillable = ['article_broadcast_id', 'title', 'description', 'text', 'image', 'image_second', 'image_third', 'image_fourth', 'image_counter', 'video', 'age_restriction', 'copyright_text', 'copyright_link', 'created_at', 'created_by', 'start', 'active', 'editors_choice', 'public_choice', 'new_episode', 'archived', 'deleted', 'main_base_id', 'export_status'];

    protected $table = 'video';

    protected $primaryKey = 'video_id';

    public $timestamps  = false;
}
