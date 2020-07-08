<?php
namespace vxPHP\Tests\Templating\Filter;

use PHPUnit\Framework\TestCase;
use vxPHP\Template\Filter\Spaceless;

class SpacelessTest extends TestCase
{
    public function templateSamples()
    {
        return [
            ['<foo> </foo>', '<foo> </foo>'],
            ['<!-- {spaceless} --><foo> </foo>', '<!-- {spaceless} --><foo> </foo>'],
            ['<!-- {spaceless} --> <!-- {endspaceless} -->', ''],
            ['<!-- {spaceless} --><foo>   </foo> <!-- {endspaceless} -->', '<foo></foo>'],
            ['<!-- {spaceless} --><foo>   </foo>  <bar> x </bar> <!-- {endspaceless} -->', '<foo></foo><bar> x </bar>'],
            ["<!-- {spaceless} -->\t<foo>   </foo>\n\t<bar> x </bar>\n\t<baz> y </baz> <!-- {endspaceless} -->", '<foo></foo><bar> x </bar><baz> y </baz>'],
        ];
    }

    /**
     * @dataProvider templateSamples
     */
    public function testApply($previous, $after)
    {
        (new Spaceless())->apply($previous);
        $this->assertEquals($after, $previous);
    }
}