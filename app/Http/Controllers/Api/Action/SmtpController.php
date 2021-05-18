<?php
namespace app\Http\Controllers\Api\Action;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Service\MailService;
use App\Service\UtilService;
use App\Rules\MyEmail;

class SmtpController extends Controller
{
    /**
     * 個別メール送信を行う
     */
    public function send(Request $request, MailService $mailServicel) {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'toAddress' => ['required', new MyEmail],
            'fromAddress' => ['required', new MyEmail],
            'fromName' => 'string',
            'cc' => 'array',
            'cc.*' => [new MyEmail],
            'bcc' => 'array',
            'bcc.*' => [new MyEmail],
            'subject' => 'required | string',
            'body' => 'required | string',
            'mimeType' => 'required | string | in:"plane", "html"',
        ], UtilService::getValidateMessage());

        // バリデーションエラー時処理
        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $key => $value) {
                // エラーレスポンス返却
                return response()->json([
                    'apiStatus' => 400,
                    'errorReasonCd' => $value[0],
                    'errorKey' => $key
                ]);
                break;
            }
        }

        try {
            // メール送信
            if ($request->get('mimeType') === "html") {

                // html
                $mailServicel->sendHtmlMail($request->get('toAddress'), $request->get('fromAddress'),
                    $request->get('fromName'), $request->get('cc', []), $request->get('bcc', []),
                    $request->get('subject'), $request->get('body'));
            } else {

                // plane
                $mailServicel->sendPlaneMail($request->get('toAddress'), $request->get('fromAddress'),
                    $request->get('fromName'), $request->get('cc', []), $request->get('bcc', []),
                    $request->get('subject'), $request->get('body'));
            }
        } catch (Exception $e) {
            \Log::channel('mailFailure')->info("[メール送信失敗] タイトル:" . $request->get('subject') .", アドレス:" . $request->get('toAddress'));
            throw $e;
        }

        \Log::channel('maillogs')->info("[メール送信完了] タイトル:" . $request->get('subject') .", アドレス:" . $request->get('toAddress'));

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200
        ]);
    }
}