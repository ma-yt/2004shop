<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XcxLogin extends Model
{
    protected $table = 'xcx';           //Model使用的表
    protected $primaryKey = 'id';      // 主键
    public $timestamps = false;
}
