<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Security;

use vxPHP\Security\Exception\CaptchaException;

/**
 * Captcha
 * @version 0.2.6, 2025-01-13
 * @author Gregor Kofler
 */
class Captcha
{
    private int $charCount;
    private int $fontSize;
    private array $bgColor = [];
    private array $fontColors = [];
    private array $fonts = [];
    private array $gridColors = [];
    private int $gridSpacing = 8;
    private bool $tiltedLetters = true;

    private string $type;
    private string $imgType;
    private string $string;
    private int $height;
    private int $width;
    private bool $debug = false;

    /**
     * @param int $charCount character count
     * @param int $fontSize font size
     * @param string $imgType image type
     * @param string $type text contain digits, chars or mixed
     * @return void
     */
    public function __construct(int $charCount = 6, int $fontSize = 32, string $imgType = 'png', string $type = 'mixed')
    {
        $this->charCount = $charCount;
        $this->fontSize = $fontSize;
        $this->imgType = $imgType;
        $this->type = $type;

        $this->width = ($this->charCount + 2) * $this->fontSize;
        $this->height = ($this->fontSize * 3);
        $this->generateString();
    }

    public function __toString()
    {
        return $this->string;
    }

    public function tiltLetters($tilt): Captcha
    {
        $this->tiltedLetters = (boolean)$tilt;
        return $this;
    }

    public function setFonts($fonts): Captcha
    {
        $this->fonts = (array)$fonts;
        return $this;
    }

    public function setBgColor($bgColor): Captcha
    {
        if (!preg_match('/^#?([0-9a-f]{6})$/i', trim($bgColor), $matches)) {
            throw new CaptchaException(sprintf("Invalid background color '%s'.", trim($bgColor)));
        }

        $this->bgColor = [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2))
        ];

        return $this;
    }

    public function setFontColor($color): Captcha
    {
        foreach ((array)$color as $c) {

            if (!preg_match('/^#?([0-9a-f]{6})$/i', trim($c), $matches)) {
                throw new CaptchaException(sprintf("Invalid font color '%s'.", trim($c)));
            }

            $this->fontColors[] = [
                hexdec(substr($matches[1], 0, 2)),
                hexdec(substr($matches[1], 2, 2)),
                hexdec(substr($matches[1], 4, 2))
            ];
        }

        return $this;
    }

    public function setGridColor($color): Captcha
    {
        foreach ((array)$color as $c) {

            if (!preg_match('/^#?([0-9a-f]{6})$/i', trim($c), $matches)) {
                throw new CaptchaException(sprintf("Invalid grid color '%s'.", trim($c)));
            }

            $this->gridColors[] = [
                hexdec(substr($matches[1], 0, 2)),
                hexdec(substr($matches[1], 2, 2)),
                hexdec(substr($matches[1], 4, 2))
            ];
        }

        return $this;
    }

    public function setGridSpacing(int $gridSpacing): Captcha
    {
        $this->gridSpacing = max(2, $gridSpacing);
        return $this;
    }

    public function display(): void
    {
        if (empty($this->fonts)) {
            throw new CaptchaException('No font(s) set.');
        }

        $image = $this->generate();

        $this->sendHeader();

        switch ($this->imgType) {

            case 'jpeg':
                imagejpeg($image);
                break;

            case 'gif':
                imagegif($image);
                break;

            case 'png':
            default:
                imagepng($image);
        }
    }

    public function save($path, $filename = null): bool
    {
        if (empty($this->fonts)) {
            throw new CaptchaException('No font(s) set.');
        }

        if (empty($filename)) {
            $filename = uniqid('captcha_', true);
        }

        $filename = rtrim($path, '/') . '/' . $filename;

        $image = $this->generate();

        return match ($this->imgType) {
            'jpeg' => imagejpeg($image, $filename),
            'gif' => imagegif($image, $filename),
            default => imagepng($image, $filename, 9),
        };
    }

    public function getString(): string
    {
        return $this->string;
    }

    private function generate()
    {
        if (!($image = imagecreatetruecolor($this->width, $this->height))) {
            throw new CaptchaException('GD image stream could not be initialized.');
        }

        if (function_exists('imageantialias')) {
            imageantialias($image, true);
        }

        if ($this->bgColor) {
            $bgColor = imagecolorallocate($image, $this->bgColor[0], $this->bgColor[1], $this->bgColor[2]);
            imagefill($image, 0, 0, $bgColor);
        }

        if (!$this->fontColors) {
            throw new CaptchaException('No font colors defined.');
        }

        $char = [];

        foreach ($this->fontColors as $color) {
            $char[] = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        }


        if ($this->gridColors) {
            $grid = [];

            foreach ($this->gridColors as $color) {
                $grid[] = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            }

            for ($i = $this->gridSpacing; $i < $this->height; $i += $this->gridSpacing) {
                imageline($image, 0, $i + random_int(-$this->gridSpacing, $this->gridSpacing), $this->width, $i + random_int(-$this->gridSpacing, $this->gridSpacing), $grid[array_rand($grid)]);
            }
        }

        $len = strlen($this->string);
        $x = $this->fontSize;
        $y = $this->fontSize * 2;

        for ($i = 0; $i < $len; ++$i) {

            if ($this->tiltedLetters) {
                $angle = random_int(0, 1) ? random_int(0, 29) : random_int(330, 360);
            } else {
                $angle = 0;
            }

            imagettftext($image, $this->fontSize, $angle, $x, $y, $char[array_rand($char)], $this->fonts[array_rand($this->fonts)], $this->string[$i]);
            $x += $this->fontSize + random_int(-$this->fontSize, $this->fontSize) / 5;
            $y += random_int(-$this->fontSize, $this->fontSize) / 4;
        }

        if ($this->gridColors) {
            for ($i = $this->gridSpacing; $i < $this->width; $i += $this->gridSpacing) {
                imageline($image, $i + random_int(-$this->gridSpacing, $this->gridSpacing), 0, $i + random_int(-$this->gridSpacing, $this->gridSpacing), $this->height, $grid[array_rand($grid)]);
            }
        }

        if ($this->debug) {
            imagestring($image, 5, 0, 0, $this->string, imagecolorallocate($image, 0, 0, 0));
        }

        return $image;
    }

    private function generateString(): void
    {
        $rv = '';
        while (strlen($rv) < $this->charCount) {

            if ($this->type === 'digits') {
                $char = random_int(0, 9);
            } else {
                $char = chr(random_int(0, 255));
            }

            $regex = match ($this->type) {
                'chars' => '/^[a-z@!?]$/i',
                'digits' => '/^[0-9]$/',
                default => '/^[a-np-z0-9@!?]$/i',
            };

            if (preg_match($regex, $char)) {
                $rv .= $char;
            }

        }
        $this->string = $rv;
    }

    private function sendHeader(): void
    {
        header('Content-type: image/' . $this->imgType);
    }
}
