<?php

namespace Templating\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use vxPHP\Template\Filter\Spaceless;

class SpacelessTest extends TestCase
{
    public static function templateSamples(): array
    {
        return [
            ['<foo> </foo>', '<foo> </foo>'],
            ['<!-- {spaceless} --><foo> </foo>', '<!-- {spaceless} --><foo> </foo>'],
            ['<!-- {spaceless} --> <!-- {endspaceless} -->', ''],
            ['<!-- {spaceless} --><foo>   </foo> <!-- {endspaceless} -->', '<foo></foo>'],
            ['<!-- {spaceless} --><foo>   </foo>  <bar> x </bar> <!-- {endspaceless} -->', '<foo></foo><bar> x </bar>'],
            ['<p> <!-- {spaceless} -->
                <foo>   </foo>
                <bar> x </bar>
                <baz> y </baz>
                <!-- {endspaceless} --> </p>',
                '<p> <foo></foo><bar> x </bar><baz> y </baz> </p>'
            ],
            ['<p> <!-- {spaceless} -->
                <foo>   </foo>
                <bar> x </bar>
                <baz> y </baz>
                <!-- {endspaceless} --> </p> <!-- {spaceless} -->
                <foo>   </foo>
                <bar> x </bar>
                <baz> y </baz>
                <!-- {endspaceless} -->',
                '<p> <foo></foo><bar> x </bar><baz> y </baz> </p> <foo></foo><bar> x </bar><baz> y </baz>'
            ],
        ];
    }

    #[DataProvider('templateSamples')]
    public function testApply($previous, $after): void
    {
        (new Spaceless())->apply($previous);
        $this->assertEquals($after, $previous);
    }
}