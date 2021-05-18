<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class Course extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'course';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * コース情報取得
     *
     * @param string $contractId 契約ID
     * @param string $customerId 顧客ID
     * @return Customer
     */
    public function getCourse(int $courseId) {
        return $this
        ->where('del_flg', 0)
        ->where('id', $courseId)
        ->select('*')
        ->first();
    }
}
