<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class VirtualBank extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'virtual_bank';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * バーチャル口座情報取得
     *
     * @param int $customerId 顧客ID
     * @return VirtualBank
     */
    public function getVirtualBank(int $customerId) {
        return $this
        ->where('del_flg', 0)
        ->where('customer_id', $customerId)
        ->select('*')
        ->first();
    }

}
