<?php
namespace app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Http\Controllers\Controller;

class TestController extends Controller
{

    public function index(Request $request) {

        try {
            DB::connection('kireimo_mysql')->select('show databases');
            echo "OK";
        } catch (Exception $e) {
            echo "NG";
        }
        echo "<br>";
        try {
            DB::connection('message_mysql')->select('show databases');
            echo "OK";
        } catch (Exception $e) {
            echo "NG";
        }
        echo "<br>";
        try {
            DB::connection('kireimo_stg_mysql')->select('show databases');
            echo "OK";
        } catch (Exception $e) {
            echo "NG";
        }
        echo "<br>";
        try {
            DB::connection('common_mysql')->select('show databases');
            echo "OK";
        } catch (Exception $e) {
            echo "NG";
        }

    }

}

