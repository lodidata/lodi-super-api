<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;
class Idcard extends AbstractRule
{
    public function validate($input)
    {
        return V::regex('/^\d{17}(\d|x)$/i')->noWhitespace()->validate($input);
    }
}