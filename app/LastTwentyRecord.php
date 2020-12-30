<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LastTwentyRecord extends Model
{
    protected $fillable = ['time', 'lat', 'long', 'magnitude', 'place'];
}
