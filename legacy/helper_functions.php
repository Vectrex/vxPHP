<?php
/**
 * Unsharp Mask for PHP - version 2.1.1
 *  Unsharp mask algorithm by Torstein Hønsi 2003-07.
 * thoensi_at_netcom_dot_no.
 */  

/**
 * New:
 * - In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements.
 * - From version 2 (July 17 2006) the script uses the imageconvolution function in PHP 
 * version >= 5.1, which improves the performance considerably.
 * 
 * The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
 * Radius is the radius of the blurring circle of the mask. 'Threshold' is the least
 * difference in colour values that is allowed between the original and the mask. In practice
 * this means that low-contrast areas of the picture are left unrendered whereas edges
 * are treated normally. This is good for pictures of e.g. skin or blue skies.
 * Any suggenstions for improvement of the algorithm, expecially regarding the speed
 * and the roundoff errors in the Gaussian blur process, are welcome.
 */

function USM($img, $amount, $radius, $threshold)    { 
    // $img is an image that is already created within php using 
    // imgcreatetruecolor. No url! $img must be a truecolor image. 
	if ($amount > 500) {
		$amount = 500;
	} 
	if ($radius > 50) {
		$radius = 50; 
	}
	if ($threshold > 255)  {
		$threshold = 255; 
	}
	$amount = $amount * 0.016; 
	$radius = abs(round($radius * 2)); 

	if ($radius == 0) { 
		return $img;
	}
	$w = imagesx($img);
	$h = imagesy($img); 
	$imgCanvas	= imagecreatetruecolor($w, $h); 
	$imgBlur	= imagecreatetruecolor($w, $h); 

    // Gaussian blur matrix: 
    //                         
    //    1    2    1         
    //    2    4    2         
    //    1    2    1         

	if (function_exists('imageconvolution')) { // PHP >= 5.1  
		imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h); 
		imageconvolution($imgBlur, array(  
			array( 1, 2, 1 ),  
			array( 2, 4, 2 ),  
			array( 1, 2, 1 )) , 16, 0);
	}  

	// Move copies of the image around one pixel at the time and merge them with weight 
	// according to the matrix. The same matrix is simply repeated for higher radii. 
	else {  
		for ($i = 0; $i < $radius; $i++)    { 
			imagecopy		($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left 
			imagecopymerge	($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right 
			imagecopymerge	($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center 
			imagecopy		($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h); 
			imagecopymerge	($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up 
			imagecopymerge	($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down 
		} 
	} 

	// Calculate the difference between the blurred pixels and the original 
	// and set the pixels 
	if($threshold > 0) { 
		for ($x = 0; $x < $w-1; $x++) {
			for ($y = 0; $y < $h; $y++) { 
				$rgbOrig = ImageColorAt($img, $x, $y); 
				$rOrig = (($rgbOrig >> 16) & 0xFF); 
				$gOrig = (($rgbOrig >> 8) & 0xFF); 
				$bOrig = ($rgbOrig & 0xFF); 

				$rgbBlur = ImageColorAt($imgBlur, $x, $y); 
				$rBlur = (($rgbBlur >> 16) & 0xFF); 
				$gBlur = (($rgbBlur >> 8) & 0xFF); 
				$bBlur = ($rgbBlur & 0xFF); 
 
				if(abs($rOrig - $rBlur) >= $threshold) {
					$rNew = $amount * ($rOrig - $rBlur) + $rOrig; 
					if		($rNew > 255)	{ $rNew = 255; } 
					elseif	($rNew < 0)		{ $rNew = 0; } 
				}
				if(abs($gOrig - $gBlur) >= $threshold) {
					$gNew = $amount * ($gOrig - $gBlur) + $gOrig; 
					if		($gNew > 255)	{ $gNew = 255; } 
					elseif	($gNew < 0)		{ $gNew = 0; } 
				}
				if(abs($bOrig - $bBlur) >= $threshold) {
					$bNew = $amount * ($bOrig - $bBlur) + $bOrig; 
					if		($bNew > 255)	{ $bNew = 255; } 
					elseif	($bNew < 0)		{ $bNew = 0; } 
				}

				ImageSetPixel($img, $x, $y, ($rNew << 16) + ($gNew << 8) + $bNew); 
			} 
		} 
    }
    else { 
		for ($x = 0; $x < $w-1; $x++) {
			for ($y = 0; $y < $h; $y++) { 
				$rgbOrig = ImageColorAt($img, $x, $y); 
				$rOrig = (($rgbOrig >> 16) & 0xFF); 
				$gOrig = (($rgbOrig >> 8) & 0xFF); 
				$bOrig = ($rgbOrig & 0xFF); 

				$rgbBlur = ImageColorAt($imgBlur, $x, $y); 
				$rBlur = (($rgbBlur >> 16) & 0xFF); 
				$gBlur = (($rgbBlur >> 8) & 0xFF); 
				$bBlur = ($rgbBlur & 0xFF); 
            	                 
				$rNew = $amount * ($rOrig - $rBlur) + $rOrig; 
				if		($rNew > 255)	{ $rNew = 255; } 
				elseif	($rNew < 0)		{ $rNew = 0; } 

				$gNew = $amount * ($gOrig - $gBlur) + $gOrig; 
				if		($gNew > 255)	{ $gNew = 255; } 
				elseif	($gNew < 0)		{ $gNew = 0; } 

				$bNew = $amount * ($bOrig - $bBlur) + $bOrig; 
				if		($bNew > 255)	{ $bNew = 255; } 
				elseif	($bNew < 0)		{ $bNew = 0; } 

				ImageSetPixel($img, $x, $y, ($rNew << 16) + ($gNew << 8) + $bNew);
			}
		}
	}
	imagedestroy($imgCanvas); 
	imagedestroy($imgBlur); 
	return $img; 
}
?>