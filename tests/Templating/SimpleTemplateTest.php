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
        $template = new SimpleTemplate();
        $template->setRawContents('<p><?= $this->date->format("Y-m-d") ?></p>');
        $template->assign('date', new \DateTime());
        $this->assertEquals('<p>' . (new \DateTime())->format('Y-m-d') . '</p>', $template->display([]));
        $template->setRawContents('<?= $this->a ?>#<?= $this->b ?>#<?= $this->c ?>#<?= $this->a ?>');
        $template->assign(['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $this->assertEquals('A#B#C#A', $template->display([]));
    }

    public function testAssignString()
    {
        $template = new SimpleTemplate();
        $template->setRawContents('<div><?= $this->text ?></div>');
        $template->assignString('text', '<p>a & o</p>');
        $this->assertEquals('<div>&lt;p&gt;a &amp; o&lt;/p&gt;</div>', $template->display([]));
    }

    public function testContainsPhp ()
    {
        $template = new SimpleTemplate();
        $template->setRawContents('<p><?php echo "Check"; ?></p>');
        $this->assertTrue($template->containsPHP());
        $template->setRawContents('<p><?= (new DateTime())->format("Y-m-d") ?></p>');
        $this->assertTrue($template->containsPHP());
        $template->setRawContents('<p>Check</p>');
        $this->assertFalse($template->containsPHP());
    }

    public function testGetParentTemplateName ()
    {
        $template = new SimpleTemplate();
        $template->setRawContents('<div><!-- {extend: parent.php@content }--></div>');
        $this->assertEquals('parent.php', $template->getParentTemplateFilename());
        $template = new SimpleTemplate();
        $template->setRawContents('<!-- a comment --><div><!-- {extend: path_to/parent.php @ content }-->');
        $this->assertEquals('path_to/parent.php', $template->getParentTemplateFilename());

    }
}