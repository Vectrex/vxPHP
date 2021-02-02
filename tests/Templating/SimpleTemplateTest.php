<?php
namespace vxPHP\Tests\Templating;

use PHPUnit\Framework\TestCase;
use vxPHP\Template\Exception\SimpleTemplateException;
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

    public function testBlockPreceededByNonWhitespaceException ()
    {
        $this->expectException(SimpleTemplateException::class);
        $this->expectExceptionMessage('First extend directive preceeded by non-whitespace characters.');

        $template = new SimpleTemplate();
        $template->setRawContents('<div><!-- {extend: parent.php@content }--></div>');
    }

    public function testBlockMismatchException ()
    {
        $this->expectException(SimpleTemplateException::class);
        $this->expectExceptionMessage('Mismatch of block markers and block contents. Block contents must not be empty.');

        $template = new SimpleTemplate();
        $template->setRawContents('<!-- {extend: parent.php@content }--> ');
    }

    public function testMultipleParentTemplateException ()
    {
        $this->expectException(SimpleTemplateException::class);
        $this->expectExceptionMessage('No support of multiple parent templates.');

        $template = new SimpleTemplate();
        $template->setRawContents('<!-- {extend: parent.php@content }--><div></div><!-- {extend: other_parent.php@content }--><div></div>');
    }

    public function testTemplateWithoutBlocks ()
    {
        $template = new SimpleTemplate();
        $template->setRawContents("\n<h1>Foo</h1>\n");
        $this->assertEquals('<h1>Foo</h1>', $template->display([]));
    }

    public function testGetParentTemplateName ()
    {
        $template = new SimpleTemplate();
        $template->setRawContents("\r\n<!-- {extend: parent.php@content }--><div>Foo</div>");
        $this->assertEquals('parent.php', $template->getParentTemplateFilename());
        $template = new SimpleTemplate();
        $template->setRawContents("<!-- {extend: path_to/parent.php @ content }--> Not empty");
        $this->assertEquals('path_to/parent.php', $template->getParentTemplateFilename());
    }

    public function testMultipleBlocksNotAllFilled ()
    {
        $parentTemplate = $this->createParentTplFile();
        $childTemplate = sprintf('<!-- { extend: %1$s @ header_block } -->
<h1>header</h1>
<!-- { extend: %1$s @ footer_block } -->
<p>footer</p>', $parentTemplate);

        $tpl = SimpleTemplate::create()->setRawContents($childTemplate);

        $this->assertEquals( <<<EOD
<head><title>Parent</title></head><body></body><header>
<h1>header</h1>
</header>
<main>

</main>
<footer>
<p>footer</p>
</footer>
EOD,
            $tpl->display([])
        );
    }

    public function testMultipleBlocks ()
    {
        $parentTemplate = $this->createParentTplFile();
        $childTemplate = sprintf('<!-- { extend: %1$s @ header_block } -->
<h1>header</h1>
<!-- { extend: %1$s @ content_block } -->
<p>content</p>
<!-- { extend: %1$s @ footer_block } -->
<p>footer</p>', $parentTemplate);

        $tpl = SimpleTemplate::create()->setRawContents($childTemplate);

        $this->assertEquals( <<<EOD
<head><title>Parent</title></head><body></body><header>
<h1>header</h1>
</header>
<main>
<p>content</p>
</main>
<footer>
<p>footer</p>
</footer>
EOD,
            $tpl->display([])
        );
    }

    protected function createParentTplFile()
    {
        $parentTempFile = tempnam(sys_get_temp_dir() . '/tpl_test', 'parent');
        file_put_contents($parentTempFile, <<<EOD
<head><title>Parent</title></head><body></body><header>
<!-- { block: header_block } -->
</header>
<main>
<!-- { block: content_block } -->
</main>
<footer>
<!-- { block: footer_block } -->
</footer>
EOD
        );

        return $parentTempFile;
    }

    protected function setUp(): void
    {
        mkdir(sys_get_temp_dir() . '/tpl_test', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/tpl_test/*') as $file) {
            unlink($file);
        }

        rmdir(sys_get_temp_dir() . '/tpl_test');
    }

}