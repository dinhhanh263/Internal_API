<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class Bank extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'bank';

    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    protected $guarded = ['id'];

    /**
     * bankテーブル情報取得
     *
     * @return Bank
     */
    public function getBank(int $customerId) {
        return $this
        ->where('customer_id', $customerId)
        ->where('del_flg', 0)
        ->select('*')
        ->first();
    }
}
