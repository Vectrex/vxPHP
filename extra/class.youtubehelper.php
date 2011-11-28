<?php
class youtubeHelper {
	private static $playOptions = array(
		'fs' => 1,
		'modestbranding' => 1,
		'autoplay' => 1
	);
	
	private function __construct() {
		
	}

	public static function buildMarkup($video) {
		
		$options = array();
		foreach(self::$playOptions as $k => $v) {
			$options[] = "$k=$v";
		}
		$options = implode('&amp;', $options);
		
		return <<< EOT
			<object width="425" height="344">
				<param name="movie" value="http://www.youtube.com/v/$video?$options"</param>
				<param name="allowFullScreen" value="true"></param>
				<param name="allowScriptAccess" value="always"></param>

				<embed src="http://www.youtube.com/v/$video?$options" type="application/x-shockwave-flash" allowfullscreen="true" width="425" height="344"></embed>
			</object>
EOT;
	}

	public static function setRelatedContent(Mysqldbi $db, $video, Array $relations) {
		
	}
}

?>