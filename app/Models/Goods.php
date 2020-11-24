<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    protected $table = 'ecs_goods';
    protected $primaryKey = 'goods_id';
    public $timestamps = false;     // created_at & updated_at
}
