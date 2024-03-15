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
class PasswordException extends \Utils\Validation\BaseValidationException
{
    protected $name = '密码';

    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}}格式不正确, 请使用英文或数字，并且长度在6至16位',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}}格式不正确, 请使用英文或数字，并且长度在6至16位',
        ],
    ];

}