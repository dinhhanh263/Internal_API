<?php

namespace App\Validation;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\EmailValidation;

class AllowNonRfcComplaintEmailValidator extends EmailValidator
{
    /**
     * 最低限、@が含まれていて、ドメインとアカウント名が含まれているメールアドレスは許可します
     * @param string          $email
     * @param EmailValidation $emailValidation
     * @return bool
     */
    public function isValid($email, EmailValidation $emailValidation): bool
    {
        // warningsとerrorのプロパティを埋める
        parent::isValid($email, $emailValidation);

        return (bool)preg_match('/^.+@.+$/', $email);
    }
}