<?php
/**
 * Captcha
 * @version 0.1.1, 2009-07-03
 * @author Gregor Kofler
 */
class Captcha {
	private $charCount;
	private $fontSize;
	private $type;
	private $imgType;

	private $string;
	private $height;
	private $width;
	private $font	= 'DomBoldBT.ttf';
	private $grid	= 9;
	private $text	= '';
	private $debug	= false;
	
	public $filename;


	/**
	 * @param int character count
	 * @param int font size
	 * @param string image type
	 * @param string text contain digits, chars or mixed
	 * @return void
	 */
	public function __construct($charCount = 6, $fontSize = 32, $imgType = 'png', $type = 'mixed') {
		$this->charCount	= $charCount;
		$this->fontSize		= $fontSize;
		$this->imgType		= $imgType;
		$this->type			= $type;

		$this->width	= ($this->charCount+4)*$this->fontSize;
		$this->height	= ($this->fontSize*3);
		$this->generateString();
	}

	public function display() {
		$image = $this->generate();

		$this->sendHeader();

		switch ($this->imgType) {
			case 'jpeg':imagejpeg($image);	break;
			case 'gif':	imagegif($image);	break;
			case 'png':
			default:	imagepng($image);	break;
		}
	}

	public function save($path, $filename = false) {
		if(empty($filename)) {
			$filename = uniqid('captcha_');
		}
		$filename = rtrim($path, '/').'/'.$filename;

		$this->filename = $filename;

		$image = $this->generate();

		switch ($this->imgType) {
			case 'jpeg':return imagejpeg($image, $filename);
			case 'gif':	return imagegif($image, $filename);

			case 'png':	
			default:	return imagepng($image, $filename, 9);
		}
	}

	public function getString() {
		return $this->string;
	}

	private function generate() {
		if(!($image = imagecreatetruecolor($this->width, $this->height))) {
			die('GD image stream kann nicht initialisiert werden.');
		}

		$bg		= imagecolorallocate($image, 255, 255, 240);
		$grid	= array(
				imagecolorallocate($image, 220, 220, 255),
				imagecolorallocate($image, 220, 220, 255),
				imagecolorallocate($image, 220, 255, 255),
				imagecolorallocate($image, 255, 100, 100),
				imagecolorallocate($image, 120, 120, 250),
				imagecolorallocate($image, 160, 200, 255),
			);
		$char	= array(
				imagecolorallocate($image, 255, 100, 100),
				imagecolorallocate($image, 120, 120, 250),
				imagecolorallocate($image, 160, 200, 255),
			);
		$black	= imagecolorallocate($image, 0, 0, 0);

		for ($i = $this->grid; $i < $this->height; $i += $this->grid) {
			imageline($image, 0, $i+rand(-5, 5), $this->width, $i+rand(-5, 5), $grid[array_rand($grid, 1)]);
		}

		$len	= strlen($this->string);
		$x		= $this->fontSize;
		$y		= $this->fontSize*2;

		putenv('GDFONTPATH=' . realpath('.'));

		for($i = 0; $i < $len; $i++) {
			if (rand(0,1))	{ imagettftext($image, $this->fontSize, rand(0,19),		$x + $this->fontSize * $i * 1.25, $y - rand(0, 5), $char[array_rand($char, 1)], $this->font, $this->string{$i} ); }
			else			{ imagettftext($image, $this->fontSize, rand(340,360),	$x + $this->fontSize * $i * 1.25, $y + rand(0, 5), $char[array_rand($char, 1)], $this->font, $this->string{$i} ); }
		}

		for ($i = $this->grid; $i < $this->width; $i += $this->grid) {
			imageline($image, $i+rand(-3, 3), 0, $i+rand(-3, 3), $this->height, $grid[array_rand($grid, 1)]);
		}
		if($this->debug) {
			imagestring($image, 5, 0, 0, $this->string, $black);
		}
		return $image;
	}

	private function generateString() {
		$rv = '';
		while(strlen($rv) < $this->charCount) {
			if ($this->type == 'digits') {
				$char = rand(0,9);
			}
			else {
				$char = chr(rand(0,255));
			}
			switch($this->type) {
				case 'chars':	$regex = '/^[a-z@!?]$/i'; break;
				case 'digits':	$regex = '/^[0-9]$/'; break;
				default:		$regex = '/^[a-np-z0-9@!?]$/i';
			}
			if(preg_match($regex, $char)) { $rv .= $char; }
		}
		$this->string = $rv;
	}

	private function sendHeader() {
		header('Content-type: image/'.$this->imgType);
	}
}
