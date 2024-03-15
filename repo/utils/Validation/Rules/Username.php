<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;

class Username extends AbstractRule
{
    public function validate($input)
    {
        $res = V::alnum('_')->noWhitespace()->length(6, 30)->validate($input);
        return $res;
    }
}