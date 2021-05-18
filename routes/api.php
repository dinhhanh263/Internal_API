<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => ['api']], function(){
//     Route::get('reservablePrefList/', 'Api\ReservablePrefListController@index');
//     Route::get('shopList/', 'Api\ShopListController@index');
//     Route::get('reservableTimeInfo/', 'Api\ReservableTimeInfoController@index');
//     Route::post('registReserve/', 'Api\RegistReserveController@postIndex');

    // メール配信系
    Route::post('v1/auth/mailAdminAuth/', 'Api\Auth\MailAdminAuthController@index');
    Route::post('v1/action/sms/send', 'Api\Action\SmsController@send');
    Route::post('v1/action/sms/checkValid', 'Api\Action\SmsController@checkValid');
    Route::post('v1/action/mail/send', 'Api\Action\MailController@send');
    Route::post('v1/action/mail/checkValid', 'Api\Action\MailController@checkValid');

    // 汎用シンプルメール送信
    Route::post('v1/action/smtp/send', 'Api\Action\SmtpController@send');

    // キレイモ系
    Route::post('v1/kireimo/auth/mypageAuth', 'Api\Kireimo\Auth\MaypageAuthController@index'); // マイページ認証
    Route::get('v1/kireimo/shops', 'Api\Kireimo\ShopController@index'); // 店舗一覧取得
    Route::get('v1/kireimo/customer/{customerId}', 'Api\Kireimo\CustomerController@get')->where('customerId', '[0-9]+'); // 顧客情報取得
//     Route::post('v1/kireimo/customer', 'Api\Kireimo\CustomerController@post'); // 顧客登録
    Route::patch('v1/kireimo/customer/{customerId}', 'Api\Kireimo\CustomerController@patch')->where('customerId', '[0-9]+'); // 顧客情報更新
    Route::put('v1/kireimo/customer/{customerId}/password', 'Api\Kireimo\PasswordController@put')->where('customerId', '[0-9]+'); // パスワード変更
    Route::get('v1/kireimo/customer/{customerId}/contracts', 'Api\Kireimo\ContractController@index')->where('customerId', '[0-9]+'); // 顧客契約一覧取得
    Route::get('v1/kireimo/customer/conditionCheck/{contractId}', 'Api\Kireimo\ConditionCheckController@index')->where('contractId', '[0-9]+'); // 契約コンディションチェック
    Route::get('v1/kireimo/customer/reservableRange/{contractId}', 'Api\Kireimo\ReservableRangeController@index')->where('contractId', '[0-9]+'); // 予約可能日付取得
    Route::get('v1/kireimo/customer/{customerId}/reservations', 'Api\Kireimo\ReservationController@index')->where('customerId', '[0-9]+'); // 顧客予約一覧取得
    Route::get('v1/kireimo/reservation/{reservationId}', 'Api\Kireimo\ReservationController@get')->where('reservationId', '[0-9]+'); // 予約情報取得
//     Route::post('v1/kireimo/reservation', 'Api\Kireimo\ReservationController@post'); // 予約登録
    Route::post('v1/kireimo/treatmentReservation', 'Api\Kireimo\ReservationController@postTreatment'); // トリートメント予約登録
    Route::put('v1/kireimo/reservation/{reservationId}', 'Api\Kireimo\ReservationController@put')->where('reservationId', '[0-9]+'); // 予約変更
    Route::put('v1/kireimo/reservation/{reservationId}/cancel', 'Api\Kireimo\ReservationController@cancel')->where('reservationId', '[0-9]+'); // 予約キャンセル
    Route::post('v1/kireimo/action/counseling/register', 'Api\Kireimo\Action\CounselingController@register'); // カウンセリング登録
    Route::get('v1/kireimo/shop/reservableCheck', 'Api\Kireimo\ReservableCheckController@counseling'); // 予約空き状況確認
    Route::get('v1/kireimo/shop/treatmentReservableCheck', 'Api\Kireimo\ReservableCheckController@treatment'); // トリートメント予約空き状況確認
    Route::get('v1/kireimo/news', 'Api\Kireimo\NewsController@get'); // ニュース一覧取得
    Route::get('v1/kireimo/nutritionQuestion', 'Api\Kireimo\NutritionQuestionController@get'); // 栄養質問一覧取得
    Route::post('v1/kireimo/nutritionQuestion/{customerId}/answers', 'Api\Kireimo\NutritionQuestionController@post')->where('customerId', '[0-9]+'); // 栄養質問回答
});