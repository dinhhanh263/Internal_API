<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;


class Ngword extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'ngword';

    public $timestamps = false;
//     const CREATED_AT = 'reg_date';
//     const UPDATED_AT = 'edit_date';


    public function getAll() {
        return $this
        ->where('status', 0)
        ->select('*')
        ->get();

    }
}
