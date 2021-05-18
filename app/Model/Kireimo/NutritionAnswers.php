<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class NutritionAnswers extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'nutrition_answers';

    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';


    /**
     * 栄養アンケート回答取得
     *
     * @return NutritionAnswers
     */
    public function getNutritionAnswers(int $customerId) {
        return $this
        ->where('del_flg', 0)
        ->where('customer_id', $customerId)
        ->select('*')
        ->orderBy('reg_date', 'DESC')
        ->first();
        //         ->toSql();
    }
}
