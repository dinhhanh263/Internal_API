<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class Contract extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'contract';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * 顧客契約一覧取得(契約終了も含む)
     *
     * @param string $customerId 顧客ID
     * @return Contract
     */
    public function getContractList(int $customerId) {
        return $this
        ->join('course', 'course.id', '=', 'contract.course_id')
        ->join('customer', 'customer.id', '=', 'contract.customer_id')
        ->where('contract.del_flg', 0)
        ->where('course.del_flg', 0)
        ->where('customer.del_flg', 0)
//         ->where('contract.status', 0)
        ->where('contract.customer_id', $customerId)
        ->select('contract.*','course.type as course_type','course.treatment_type as course_treatment_type','course.name as course_name'
            , 'course.zero_flg as course_zero_flg', 'course.new_flg as course_new_flg'
            , 'course.interval_date as course_interval_date'
            , 'course.sales_start_date as course_sales_start_date', 'course.sales_end_date as course_sales_end_date'
            , 'course.reservation_max_times as course_reservation_max_times', 'course.minor_plan_flg as course_minor_plan_flg')
        ->get();
    }

    /**
     * 契約情報取得
     *
     * @param string $contractId 契約ID
     * @return Contract
     */
    public function getContract(int $contractId) {
        return $this
        ->where('del_flg', 0)
        ->where('id', $contractId)
        ->select('*')
        ->first();
    }

    /**
     * 契約情報取得(契約中のみ)
     *
     * @param string $contractId 契約ID
     * @return Contract
     */
    public function getActiveContract(int $contractId) {
        return $this
        ->join('course', 'course.id', '=', 'contract.course_id')
        ->where('contract.status', 0)
        ->where('contract.del_flg', 0)
        ->where('contract.id', $contractId)
        ->select('contract.*', 'course.type as course_type', 'course.length as course_length')
        ->first();
    }
}
