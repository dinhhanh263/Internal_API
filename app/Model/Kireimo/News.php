<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class News extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'news';


    /**
     * newsテーブル情報取得
     *
     * @return News
     */
    public function getNews() {
        return $this
        ->where('del_flg', 0)
        ->whereRaw('start_date <= now()')
        ->whereRaw('end_date >= now()')
        ->select('*')
        ->orderBy('weight', 'DESC')
        ->orderBy('start_date', 'DESC')
        ->orderBy('id', 'DESC')
        ->get();
//         ->toSql();
    }
}
