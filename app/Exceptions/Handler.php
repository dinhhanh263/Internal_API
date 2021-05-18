<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // 独自例外の場合
        if($exception instanceof MyApiException) {
            return response()->json([
                'apiStatus' => $exception->apiStatus,
                'errorReasonCd' => $exception->errorReasonCd,
                'errorKey' => $exception->errorKey
            ], 200);
        }

        // APIへのリクエストの場合レスポンスをJSON形式に変更
        if ($request->is('api/*')) {
            $status = 400;
            if ($this->isHttpException($exception)) {
                $status = $exception->getStatusCode();
            }

            $message = $exception->getMessage();

            // バリデーションエラーの場合、エラー配列を文字列化
            if($exception instanceof ValidationException) {
                $errors = $exception->errors();
                foreach ($errors as $key => $error) {
                    $errors[$key] = implode($error);
                }
                $message = $errors;
            }

            if ($status === 405) {
                return response()->json([
                    'apiStatus' => 405,
                    'errorReasonCd' => null,
                    'errorKey' => null,
//                     'errorMessage' => is_array($message) ? implode($message) :$message
                ], $status);
            }

            return response()->json([
                'apiStatus' => $status === 404 ?  $status : 500,
                'errorReasonCd' => 'E5001',
                'errorKey' => null,
//                 'errorMessage' => is_array($message) ? implode($message) :$message
            ], $status);
        }

        return parent::render($request, $exception);
    }
}
