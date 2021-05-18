<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class Adcode extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'adcode';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * adcodeId取得(adcodeから)
     *
     * @param string $adcode adcode
     * @return Adcode
     */
    public function getAdcodeId(string $adcode) {
        return $this
        ->where('del_flg', 0)
        ->where('adcode', $adcode)
        ->select('*')
        ->first();
    }

}
