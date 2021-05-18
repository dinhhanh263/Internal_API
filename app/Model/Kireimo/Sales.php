<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class Sales extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'sales';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';


    /**
     * 売上情報取得
     *
     * @param string $contractId 売上ID
     * @return Sales
     */
    public function getSales(int $salesId) {
        return $this
        ->where('del_flg', 0)
        ->where('id', $salesId)
        ->select('*')
        ->first();
    }

}
