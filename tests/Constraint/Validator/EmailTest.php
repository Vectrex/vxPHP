<?php
namespace vxPHP\Tests\Constraint;

use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\Email;

class EmailTest extends TestCase {

    /* taken from https://gist.github.com/cjaoude/fd9910626629b53c4d25 */

    protected $validEmails = [
        'email@example.com',
        'firstname.lastname@example.com',
        'email@subdomain.example.com',
        'firstname+lastname@example.com',
        'email@123.123.123.123',
        'email@[123.123.123.123]',
        '"email"@example.com',
        '1234567890@example.com',
        'email@example-one.com',
        '_______@example.com',
        'email@example.name',
        'email@example.museum',
        'email@example.co.jp',
        'firstname-lastname@example.com',

        'much.”more\ unusual”@example.com',
        'very.unusual.”@”.unusual.com@example.com',
        'very.”(),:;<>[]”.VERY.”very@\\ "very”.unusual@strange.example.com'
    ];

    protected $invalidEmails = [
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
        'email@example..com',
        'Abc..123@example.com',

        '”(),:;<>[\]@example.com',
        'just”not”right@example.com',
        'this\ is"really"not\allowed@example.com'
    ];

    public function validEmailStrings ()
    {
        $values = [];
        foreach($this->validEmails as $value) {
            $values[] = [$value];
        }
        return $values;
    }

    public function invalidEmailStrings ()
    {
        $values = [];
        foreach($this->invalidEmails as $value) {
            $values[] = [$value];
        }
        return $values;
    }

    /**
     * @dataProvider validEmailStrings
     */
    public function testValidEmails($value)
    {
        //$this->assertTrue(true);
        $this->assertTrue((new Email())->validate($value), sprintf('%s is a valid e-mail address.', $value));
    }

    /**
     * @dataProvider invalidEmailStrings
     */
    public function testInvalidEmails($value)
    {
        //$this->assertTrue(true);
        $this->assertFalse((new Email())->validate($value), sprintf('%s is an invalid e-mail address.', $value));
    }
}
