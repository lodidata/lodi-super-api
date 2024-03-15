<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Utils\Validation\Exceptions;
class UsernameLoginException extends \Utils\Validation\BaseValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} 还没有注册',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} 还没有注册',
        ],
    ];

}
