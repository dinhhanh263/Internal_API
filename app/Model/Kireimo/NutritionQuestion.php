<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class NutritionQuestion extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'nutrition_questionnaires';


    /**
     * 栄養アンケート質問テーブル情報取得
     *
     * @return NutritionQuestion
     */
    public function getNutritionQuestion() {
        return $this
        ->where('del_flg', 0)
        ->select('*')
        ->get();
//         ->toSql();
    }
}
