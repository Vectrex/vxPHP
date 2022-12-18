<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * with minor adaptations lifted from Symfony's HttpFoundation classes
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace vxPHP\Http;

use vxPHP\File\Exception\FilesystemFileException;
use vxPHP\File\FilesystemFile;

/**
 * BinaryFileResponse represents an HTTP response delivering a file.
 *
 * @author Niklas Fiekas <niklas.fiekas@tu-clausthal.de>
 * @author stealth35 <stealth35-php@live.fr>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Sergey Linnik <linniksa@gmail.com>
 * @author Gregor Kofler
 */
class BinaryFileResponse extends Response
{
    protected static bool $trustXSendfileTypeHeader = false;

    /**
     * @var FilesystemFile
     */
    protected FilesystemFile $file;
    protected int $offset = 0;
    protected int $maxlen = -1;
    protected bool $deleteFileAfterSend = false;

    /**
     * @param FilesystemFile $file The file to stream
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param bool $public Files are public by default
     * @param string|null $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool $autoEtag Whether the ETag header should be automatically set
     * @param bool $autoLastModified Whether the Last-Modified header should be automatically set
     * @throws FilesystemFileException
     */
    public function __construct(FilesystemFile $file, int $status = 200, array $headers = [], bool $public = true, string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true)
    {
        parent::__construct(null, $status, $headers);

        $this->setFile($file, $contentDisposition, $autoEtag, $autoLastModified);

        if ($public) {
            $this->setPublic();
        }
    }

    /**
     * @param FilesystemFile $content The file to stream
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param bool $public Files are public by default
     * @param string|null $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool $autoEtag Whether the ETag header should be automatically set
     * @param bool $autoLastModified Whether the Last-Modified header should be automatically set
     *
     * @return static
     * @throws FilesystemFileException
     */
    public static function create($content = null, $status = 200, $headers = [], bool $public = true, string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true): Response
    {
        return new static($content, $status, $headers, $public, $contentDisposition, $autoEtag, $autoLastModified);
    }

    /**
     * Sets the file to stream.
     *
     * @param FilesystemFile $file The file to stream
     * @param string|null $contentDisposition
     * @param bool $autoEtag
     * @param bool $autoLastModified
     *
     * @return $this
     *
     * @throws FilesystemFileException
     */
    public function setFile(FilesystemFile $file, string $contentDisposition = null, bool $autoEtag = false, bool $autoLastModified = true): self
    {
        if (!$file->getFileInfo()->isReadable()) {
            throw new FilesystemFileException('File must be readable.');
        }

        $this->file = $file;

        if ($autoEtag) {
            $this->setAutoEtag();
        }

        if ($autoLastModified) {
            $this->setAutoLastModified();
        }

        if ($contentDisposition) {
            $this->setContentDisposition($contentDisposition);
        }

        return $this;
    }

    /**
     * Gets the file.
     *
     * @return FilesystemFile The file to stream
     */
    public function getFile(): FilesystemFile
    {
        return $this->file;
    }

    /**
     * Automatically sets the Last-Modified header according the file modification date.
     */
    public function setAutoLastModified(): self
    {
        $this->setLastModified(\DateTime::createFromFormat('U', $this->file->getFileInfo()->getMTime()));

        return $this;
    }

    /**
     * Automatically sets the ETag header according to the checksum of the file.
     */
    public function setAutoEtag(): self
    {
        $this->setEtag(base64_encode(hash_file('sha256', $this->file->getFileInfo()->getPathname(), true)));

        return $this;
    }

    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $disposition      ResponseHeaderBag::DISPOSITION_INLINE or ResponseHeaderBag::DISPOSITION_ATTACHMENT
     * @param string $filename         Optionally use this UTF-8 encoded filename instead of the real name of the file
     * @param string $filenameFallback A fallback filename, containing only ASCII characters. Defaults to an automatically encoded filename
     *
     * @return $this
     */
    public function setContentDisposition(string $disposition, string $filename = '', string $filenameFallback = ''): self
    {
        if ('' === $filename) {
            $filename = $this->file->getFilename();
        }

        if ('' === $filenameFallback && (!preg_match('/^[\x20-\x7e]*$/', $filename) || false !== strpos($filename, '%'))) {
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';

            for ($i = 0, $filenameLength = mb_strlen($filename, $encoding); $i < $filenameLength; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);

                if ('%' === $char || \ord($char) < 32 || \ord($char) > 126) {
                    $filenameFallback .= '_';
                } else {
                    $filenameFallback .= $char;
                }
            }
        }

        $dispositionHeader = $this->headers->makeDisposition($disposition, $filename, $filenameFallback);
        $this->headers->set('Content-Disposition', $dispositionHeader);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request): Response
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->file->getMimeType() ?: 'application/octet-stream');
        }

        if ('HTTP/1.0' !== $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        $this->ensureIEOverSSLCompatibility($request);

        $this->offset = 0;
        $this->maxlen = -1;

        if (false === $fileSize = $this->file->getFileInfo()->getSize()) {
            return $this;
        }
        $this->headers->set('Content-Length', $fileSize);

        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe() ? 'bytes' : 'none');
        }

        if (self::$trustXSendfileTypeHeader && $request->headers->has('X-Sendfile-Type')) {
            // Use X-Sendfile, do not send any content.
            $type = $request->headers->get('X-Sendfile-Type');
            $path = $this->file->getPath();

            // @todo allow stream wrapped locations

            if ('x-accel-redirect' === strtolower($type)) {
                // Do X-Accel-Mapping substitutions.
                // @link https://www.nginx.com/resources/wiki/start/topics/examples/x-accel/#x-accel-redirect
                $parts = HeaderUtils::split($request->headers->get('X-Accel-Mapping', ''), ',=');
                foreach ($parts as $part) {
                    [$pathPrefix, $location] = $part;
                    if (strpos($path, $pathPrefix) === 0) {
                        $path = $location.substr($path, \strlen($pathPrefix));
                        // Only set X-Accel-Redirect header if a valid URI can be produced
                        // as nginx does not serve arbitrary file paths.
                        $this->headers->set($type, $path);
                        $this->maxlen = 0;
                        break;
                    }
                }
            } else {
                $this->headers->set($type, $path);
                $this->maxlen = 0;
            }
        } elseif ($request->headers->has('Range')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->hasValidIfRangeHeader($request->headers->get('If-Range'))) {
                $range = $request->headers->get('Range');

                [$start, $end] = explode('-', substr($range, 6), 2) + [0];

                $end = ('' === $end) ? $fileSize - 1 : (int) $end;

                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end = $fileSize - 1;
                } else {
                    $start = (int) $start;
                }

                if ($start <= $end) {
                    if ($start < 0 || $end > $fileSize - 1) {
                        $this->setStatusCode(416);
                        $this->headers->set('Content-Range', sprintf('bytes */%s', $fileSize));
                    } elseif (0 !== $start || $end !== $fileSize - 1) {
                        $this->maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                        $this->offset = $start;

                        $this->setStatusCode(206);
                        $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
                        $this->headers->set('Content-Length', $end - $start + 1);
                    }
                }
            }
        }

        return $this;
    }

    private function hasValidIfRangeHeader(?string $header): bool
    {
        if ($this->getEtag() === $header) {
            return true;
        }

        if (null === $lastModified = $this->getLastModified()) {
            return false;
        }

        return $lastModified->format('D, d M Y H:i:s').' GMT' === $header;
    }

    /**
     * Sends the file.
     *
     * {@inheritdoc}
     */
    public function sendContent(): Response
    {
        if (!$this->isSuccessful()) {
            return parent::sendContent();
        }

        if (0 === $this->maxlen) {
            return $this;
        }

        $out = fopen('php://output', 'wb');
        $file = fopen($this->file->getPath(), 'rb');

        stream_copy_to_stream($file, $out, $this->maxlen, $this->offset);

        fclose($out);
        fclose($file);

        if ($this->deleteFileAfterSend && file_exists($this->file->getPath())) {
            $this->file->delete();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content): Response
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryFileResponse instance.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return false;
    }

    /**
     * Trust X-Sendfile-Type header.
     */
    public static function trustXSendfileTypeHeader(): void
    {
        self::$trustXSendfileTypeHeader = true;
    }

    /**
     * If this is set to true, the file will be unlinked after the request is sent
     * Note: If the X-Sendfile header is used, the deleteFileAfterSend setting will not be used.
     *
     * @param bool $shouldDelete
     *
     * @return $this
     */
    public function deleteFileAfterSend(bool $shouldDelete = true): self
    {
        $this->deleteFileAfterSend = $shouldDelete;
        return $this;
    }
}
