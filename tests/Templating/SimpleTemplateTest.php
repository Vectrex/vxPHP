<?php
namespace vxPHP\Tests\Templating;

use PHPUnit\Framework\TestCase;
use vxPHP\Application\Application;
use vxPHP\Application\Config;
use vxPHP\Template\SimpleTemplate;

class SimpleTemplateTest extends TestCase
{
    public function testAssign()
    {
        $this->assertTrue(true);
    }

    public function testAssignString()
    {
        $template = new SimpleTemplate();
        $template->setRawContents('<div><?= $this->text ?></div>');
        $template->assignString('text', '<p>a & o</p>');
        $this->assertEquals('<div>&lt;p&gt;a &amp; o&lt;/p&gt;</div>', $template->display([]));
    }
}