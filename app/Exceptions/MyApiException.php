<?php

namespace App\Exceptions;

use Exception;

class MyApiException extends Exception {
    public $apiStatus;
    public $errorReasonCd;
    public $errorKey;

    public function __construct($apiStatus = 500, $errorReasonCd = null, $errorKey = null, $message = null) {
        $this->apiStatus = $apiStatus;
        $this->errorReasonCd = $errorReasonCd;
        $this->errorKey = $errorKey;
        parent::__construct($message);
    }

}