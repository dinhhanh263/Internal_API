<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class Introducer extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'introducer';

//     public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';


    /**
     * データ存在チェック
     *
     * @param int $customerId 顧客ID
     * @param int $inviteCustomerId 紹介者顧客ID
     *
     * @return boolean true:存在する false:存在しない
     */
    public function isExist(int $customerId, int $inviteCustomerId) {
        $resutl = $this
        ->where('del_flg', 0)
        ->where('customer_id', $inviteCustomerId)
        ->where('introducer_customer_id', $customerId)
        ->select('*')
        ->first();

        if (!empty($resutl)) {
            return true;
        }
        return false;
    }
}
