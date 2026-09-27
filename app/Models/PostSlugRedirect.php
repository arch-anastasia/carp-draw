<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSlugRedirect extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'slug';

    protected $keyType = 'string';

    protected $fillable = ['slug', 'post_id'];
}
