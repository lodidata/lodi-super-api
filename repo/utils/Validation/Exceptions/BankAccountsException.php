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
class BankAccountsException extends \Utils\Validation\BaseValidationException
{
    public $name = '银行卡';

    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}}不正确',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}}不正确',
        ],
    ];

}