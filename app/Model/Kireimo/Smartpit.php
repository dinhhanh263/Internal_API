<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class Smartpit extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'smartpit';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * スマートピット情報取得
     *
     * @param int $smartpitId スマートピットID
     * @return Smartpit
     */
    public function getSmartpit(int $smartpitId) {
        return $this
        ->where('del_flg', 0)
        ->where('give_flg', 1)
        ->where('id', $smartpitId)
        ->select('*')
        ->first();
    }

}
