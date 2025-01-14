<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Http;

use PHPUnit\Framework\TestCase;
use vxPHP\File\UploadedFile;
use vxPHP\Http\FileBag;

/**
 * FileBagTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class FileBagTest extends TestCase
{
    public function testFileMustBeAnArrayOrUploadedFile(): void
    {
        $this->expectException('InvalidArgumentException');
        new FileBag(['file' => 'foo']);
    }

    public function testShouldConvertsUploadedFiles(): void
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile));

        $bag = new FileBag(['file' => [
            'name' => basename($tmpFile),
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'full_path' => $tmpFile,
            'error' => 0,
            'size' => null,
        ]]);

        $this->assertEquals($file, $bag->get('file'));
    }

    public function testShouldSetEmptyUploadedFilesToNull(): void
    {
        $bag = new FileBag(['file' => [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'full_path' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]]);

        $this->assertNull($bag->get('file'));
    }

    public function testShouldRemoveEmptyUploadedFilesForMultiUpload(): void
    {
        $bag = new FileBag(['files' => [
            'name' => [''],
            'type' => [''],
            'tmp_name' => [''],
            'full_path' => [''],
            'error' => [UPLOAD_ERR_NO_FILE],
            'size' => [0],
        ]]);

        $this->assertSame([], $bag->get('files'));
    }

    public function testShouldNotRemoveEmptyUploadedFilesForAssociativeArray(): void
    {
        $bag = new FileBag(['files' => [
            'name' => ['file1' => ''],
            'type' => ['file1' => ''],
            'tmp_name' => ['file1' => ''],
            'full_path' => ['file1' => ''],
            'error' => ['file1' => UPLOAD_ERR_NO_FILE],
            'size' => ['file1' => 0],
        ]]);

        $this->assertSame(['file1' => null], $bag->get('files'));
    }

    public function testShouldConvertUploadedFilesWithPhpBug(): void
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile));

        $bag = new FileBag([
            'child' => [
                'name' => [
                    'file' => basename($tmpFile),
                ],
                'type' => [
                    'file' => 'text/plain',
                ],
                'tmp_name' => [
                    'file' => $tmpFile,
                ],
                'full_path' => [
                    'file' => $tmpFile,
                ],
                'error' => [
                    'file' => 0,
                ],
                'size' => [
                    'file' => null,
                ],
            ],
        ]);

        $files = $bag->all();
        $this->assertEquals($file, $files['child']['file']);
    }

    public function testShouldConvertNestedUploadedFilesWithPhpBug(): void
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile));

        $bag = new FileBag([
            'child' => [
                'name' => [
                    'sub' => ['file' => basename($tmpFile)],
                ],
                'type' => [
                    'sub' => ['file' => 'text/plain'],
                ],
                'tmp_name' => [
                    'sub' => ['file' => $tmpFile],
                ],
                'full_path' => [
                    'sub' => ['file' => $tmpFile],
                ],
                'error' => [
                    'sub' => ['file' => 0],
                ],
                'size' => [
                    'sub' => ['file' => null],
                ],
            ],
        ]);

        $files = $bag->all();
        $this->assertEquals($file, $files['child']['sub']['file']);
    }

    public function testShouldNotConvertNestedUploadedFiles(): void
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile));
        $bag = new FileBag(['image' => ['file' => $file]]);

        $files = $bag->all();
        $this->assertEquals($file, $files['image']['file']);
    }

    protected function createTempFile(): false|string
    {
        $tempFile = tempnam(sys_get_temp_dir() . '/form_test', 'FormTest');
        file_put_contents($tempFile, '1');

        return $tempFile;
    }

    protected function setUp(): void
    {
        mkdir(sys_get_temp_dir() . '/form_test', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/form_test/*') as $file) {
            unlink($file);
        }

        rmdir(sys_get_temp_dir() . '/form_test');
    }
}
