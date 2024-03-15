<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;

class Password extends AbstractRule
{
    public function validate($input)
    {
        $res = V::alnum()->noWhitespace()->length(6, 16)->validate($input);
        return $res;
    }
}