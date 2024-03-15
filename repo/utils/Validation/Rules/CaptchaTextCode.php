<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;

class CaptchaTextCode extends AbstractRule
{
    public function validate($input)
    {
        global $app;
        $ci = $app->getContainer();
        $res = V::alnum()->noWhitespace()->length($ci->get('settings')['capatch']['length'])->validate($input);
        return $res;
    }
}