<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class Basic extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'basic';


    /**
     * basicテーブル情報取得
     *
     * @return Basic
     */
    public function getBasic(int $id) {
        return $this
        ->where('id', $id)
        ->select('value')
        ->first();
    }
}
