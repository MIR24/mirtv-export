<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $tag_id
 * @property string $name
 */
class Tag extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tag';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'tag_id';

    /**
     * @var array
     */
    protected $fillable = ['name'];

}
