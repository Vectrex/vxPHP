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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use vxPHP\File\FilesystemFile;
use vxPHP\Http\BinaryFileResponse;
use vxPHP\Http\ResponseHeaderBag;
use vxPHP\Http\Request;

class BinaryFileResponseTest extends TestCase
{
    public function testConstruction(): void
    {
        $file = new FilesystemFile(__DIR__ . '/BinaryFileResponseTest.php');
        $response = new BinaryFileResponse($file, 404, ['X-Header' => 'Foo'], true, null, true, true);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Foo', $response->headers->get('X-Header'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('Last-Modified'));
        $this->assertFalse($response->headers->has('Content-Disposition'));

        $response = BinaryFileResponse::create($file, 404, [], true, ResponseHeaderBag::DISPOSITION_INLINE);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->headers->has('ETag'));
        $this->assertEquals('inline; filename=BinaryFileResponseTest.php', $response->headers->get('Content-Disposition'));
    }

    public function testConstructWithNonAsciiFilename(): void
    {
        touch(sys_get_temp_dir() . '/fööö.html');

        $response = new BinaryFileResponse(new FilesystemFile(sys_get_temp_dir() . '/fööö.html'), 200, [], true, 'attachment');

        @unlink(sys_get_temp_dir() . '/fööö.html');

        $this->assertSame('fööö.html', $response->getFile()->getFilename());
    }

    public function testSetContent(): void
    {
        $this->expectException('LogicException');
        $response = new BinaryFileResponse(new FilesystemFile(__FILE__));
        $response->setContent('foo');
    }

    public function testGetContent(): void
    {
        $response = new BinaryFileResponse(new FilesystemFile(__FILE__));
        $this->assertFalse($response->getContent());
    }

    public function testSetContentDispositionGeneratesSafeFallbackFilename(): void
    {
        $response = new BinaryFileResponse(new FilesystemFile(__FILE__));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'föö.html');

        $this->assertSame('attachment; filename=f__.html; filename*=utf-8\'\'f%C3%B6%C3%B6.html', $response->headers->get('Content-Disposition'));
    }

    public function testSetContentDispositionGeneratesSafeFallbackFilenameForWronglyEncodedFilename(): void
    {
        $response = new BinaryFileResponse(new FilesystemFile(__FILE__));

        $iso88591EncodedFilename = mb_convert_encoding('föö.html', 'ISO-8859-1', 'UTF-8');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $iso88591EncodedFilename);

        // the parameter filename* is invalid in this case (rawurldecode('f%F6%F6') does not provide a UTF-8 string but an ISO-8859-1 encoded one)
        $this->assertSame('attachment; filename=f__.html; filename*=utf-8\'\'f%F6%F6.html', $response->headers->get('Content-Disposition'));
    }

    #[DataProvider('provideRanges')]
    public function testRequests($requestRange, $offset, $length, $responseRange): void
    {
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // do a request to get the ETag
        $request = Request::create('/');
        $response->prepare($request);
        $etag = $response->headers->get('ETag');

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', $etag);
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__ . '/Fixtures/test.gif', 'rb');
        fseek($file, $offset);
        $data = fread($file, $length);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals($responseRange, $response->headers->get('Content-Range'));
        $this->assertSame((string)$length, $response->headers->get('Content-Length'));
    }

    #[DataProvider('provideRanges')]
    public function testRequestsWithoutEtag($requestRange, $offset, $length, $responseRange): void
    {
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream']);

        // do a request to get the LastModified
        $request = Request::create('/');
        $response->prepare($request);
        $lastModified = $response->headers->get('Last-Modified');

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', $lastModified);
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__ . '/Fixtures/test.gif', 'rb');
        fseek($file, $offset);
        $data = fread($file, $length);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals($responseRange, $response->headers->get('Content-Range'));
    }

    public static function provideRanges(): array
    {
        return [
            ['bytes=1-4', 1, 4, 'bytes 1-4/35'],
            ['bytes=-5', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-30', 30, 1, 'bytes 30-30/35'],
            ['bytes=30-34', 30, 5, 'bytes 30-34/35'],
        ];
    }

    public function testRangeRequestsWithoutLastModifiedDate(): void
    {
        // prevent auto last modified
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream'], true, null, false, false);

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', date('D, d M Y H:i:s') . ' GMT');
        $request->headers->set('Range', 'bytes=1-4');

        $this->expectOutputString(file_get_contents(__DIR__ . '/Fixtures/test.gif'));
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Content-Range'));
    }

    #[DataProvider('provideFullFileRanges')]
    public function testFullFileRequests($requestRange): void
    {
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__ . '/Fixtures/test.gif', 'rb');
        $data = fread($file, 35);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public static function provideFullFileRanges(): array
    {
        return [
            ['bytes=0-'],
            ['bytes=0-34'],
            ['bytes=-35'],
            // Syntactical invalid range-request should also return the full resource
            ['bytes=20-10'],
            ['bytes=50-40'],
        ];
    }

    public function testUnpreparedResponseSendsFullFile(): void
    {
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'));

        $data = file_get_contents(__DIR__ . '/Fixtures/test.gif');

        $this->expectOutputString($data);
        $response = clone $response;
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[DataProvider('provideInvalidRanges')]
    public function testInvalidRequests($requestRange): void
    {
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(416, $response->getStatusCode());
        $this->assertEquals('bytes */35', $response->headers->get('Content-Range'));
    }

    public static function provideInvalidRanges(): array
    {
        return [
            ['bytes=-40'],
            ['bytes=30-40'],
        ];
    }

    public function testXSendfile(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Sendfile-Type', 'X-Sendfile');

        BinaryFileResponse::trustXSendfileTypeHeader();
        $response = BinaryFileResponse::create(new FilesystemFile(__FILE__), 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->expectOutputString('');
        $response->sendContent();

        $this->assertStringContainsString('BinaryFileResponseTest.php', $response->headers->get('X-Sendfile'));
    }

    #[DataProvider('getSampleXAccelMappings')]
    public function testXAccelMapping($realpath, $mapping, $virtual): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Sendfile-Type', 'X-Accel-Redirect');
        $request->headers->set('X-Accel-Mapping', $mapping);

        $file = new FakeFile($realpath);

        BinaryFileResponse::trustXSendfileTypeHeader();
        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'application/octet-stream']);
        $reflection = new \ReflectionObject($response);
        $property = $reflection->getProperty('file');
        $property->setValue($response, $file);

        $response->prepare($request);
        $this->assertEquals($virtual, $response->headers->get('X-Accel-Redirect'));
    }

    public function testDeleteFileAfterSend(): void
    {
        $request = Request::create('/');

        $path = __DIR__ . '/Fixtures/to_delete';
        touch($path);
        $realPath = realpath($path);
        $this->assertFileExists($realPath);

        $response = new BinaryFileResponse(new FilesystemFile($realPath), 200, ['Content-Type' => 'application/octet-stream']);
        $response->deleteFileAfterSend();

        $response->prepare($request);
        $response->sendContent();

        $this->assertFileDoesNotExist($path);
    }

    public function testAcceptRangeOnUnsafeMethods(): void
    {
        $request = Request::create('/', 'POST');
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->assertEquals('none', $response->headers->get('Accept-Ranges'));
    }

    public function testAcceptRangeNotOverriden(): void
    {
        $request = Request::create('/', 'POST');
        $response = BinaryFileResponse::create(new FilesystemFile(__DIR__ . '/Fixtures/test.gif'), 200, ['Content-Type' => 'application/octet-stream']);
        $response->headers->set('Accept-Ranges', 'foo');
        $response->prepare($request);

        $this->assertEquals('foo', $response->headers->get('Accept-Ranges'));
    }

    public static function getSampleXAccelMappings(): array
    {
        return [
            ['/var/www/var/www/files/foo.txt', '/var/www/=/files/', '/files/var/www/files/foo.txt'],
            ['/home/Foo/bar.txt', '/var/www/=/files/,/home/Foo/=/baz/', '/baz/bar.txt'],
            ['/home/Foo/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', '/baz/bar.txt'],
            ['/tmp/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', null],
        ];
    }

    protected function provideResponse(): BinaryFileResponse
    {
        return new BinaryFileResponse(new FilesystemFile(__DIR__ . '/FakeFile.php'), 200, ['Content-Type' => 'application/octet-stream']);
    }

    public static function tearDownAfterClass(): void
    {
        $path = __DIR__ . '/../Fixtures/to_delete';
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

class FakeFile extends FilesystemFile
{
    private string $realpath;

    public function __construct($realpath)
    {
        $this->realpath = $realpath;
        $this->fileInfo = new FakeFileInfo();
    }

    public function getPath(): string
    {
        return $this->realpath;
    }

    public function getFilename(): string
    {
        return pathinfo($this->realpath, PATHINFO_BASENAME);
    }
}

class FakeFileInfo extends \SplFileInfo
{
    public function __construct() {}

    public function getSize(): int
    {
        return 42;
    }

    public function getMTime(): int
    {
        return time();
    }

    public function isReadable(): bool
    {
        return true;
    }
}