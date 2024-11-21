<?php

namespace Constraint\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\Email;

class EmailTest extends TestCase
{

    /* taken from https://gist.github.com/cjaoude/fd9910626629b53c4d25 */

    protected static array $validEmails = [
        'email@example.com',
        'firstname.lastname@example.com',
        'email@subdomain.example.com',
        'firstname+lastname@example.com',
        'email@[123.123.123.123]',
        '"email"@example.com',
        '1234567890@example.com',
        'email@example-one.com',
        '_______@example.com',
        'email@example.name',
        'email@example.museum',
        'email@example.co.jp',
        'firstname-lastname@example.com',
    ];

    protected static array $validEmailsNotAccepted = [
        'very.”(),:;<>[]”.VERY.”very@\\ "very”.unusual@strange.example.com',
        'email@123.123.123.123',
        'much.”more\ unusual”@example.com',
        'very.unusual.”@”.unusual.com@example.com',
    ];

    protected static array $invalidEmails = [
        'plainaddress',
        '#@%^%#$@#$@#.com',
        '@example.com',
        'Joe Smith <email@example.com>',
        'email.example.com',
        'email@example@example.com',
        '.email@example.com',
        'email.@example.com',
        'email..email@example.com',
        'あいうえお@example.com',
        'email@example.com (Joe Smith)',
        'email@example',
        'email@-example.com',
        'email@example.web',
        'email@111.222.333.44444',
        'email@999.888.777.666',
        'email@[999.888.777.666]',
        'email@example..com',
        'Abc..123@example.com',
        '”(),:;<>[\]@example.com',
        'just”not”right@example.com',
        'this\ is"really"not\allowed@example.com'
    ];

    public static function validEmailStrings(): array
    {
        $values = [];
        foreach (self::$validEmails as $value) {
            $values[] = [$value];
        }
        return $values;
    }

    public static function validEmailStringsNotAccepted(): array
    {
        $values = [];
        foreach (self::$validEmailsNotAccepted as $value) {
            $values[] = [$value];
        }
        return $values;
    }

    public static function invalidEmailStrings(): array
    {
        $values = [];
        foreach (self::$validEmailsNotAccepted as $value) {
            $values[] = [$value];
        }
        return $values;
    }

    #[DataProvider('validEmailStrings')]
    public function testValidEmails($value): void
    {
        //$this->assertTrue(true);
        $this->assertTrue((new Email())->validate($value), sprintf('%s is a valid e-mail address.', $value));
    }

    #[DataProvider('validEmailStringsNotAccepted')]
    public function testValidEmailsToError($value): void
    {
        $this->assertFalse((new Email())->validate($value), sprintf('%s is a valid e-mail address which should NOT be matched.', $value));
    }

    #[DataProvider('invalidEmailStrings')]
    public function testInvalidEmails($value): void
    {
        //$this->assertTrue(true);
        $this->assertFalse((new Email())->validate($value), sprintf('%s is an invalid e-mail address.', $value));
    }
}
