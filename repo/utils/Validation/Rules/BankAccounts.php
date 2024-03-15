<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;

class BankAccounts extends AbstractRule
{
    public function validate($input)
    {
        $res = V::noWhitespace()->length(15, 19)->validate($input);
        return $res;
    }
}