<?php

namespace vxPHP\Services\Youtube;

use vxPHP\Services\Youtube\Exception\UploaderException;

/**
 * YouTube Uploader Class
 * 
 * upload videos to youtube account
 * 
 * requires credentials file as XML file
 * with email, password and developer key, and optional source application of account 
 * 
 * @author gregor kofler
 * @version 0.6.0 2011-07-26
 */

class Uploader {
	private $authHost = 'https://www.google.com/youtube/accounts/ClientLogin',
			$authURL,
			$authPath,
			$auth,
			$youtubeUser;
			
	private $uploadHost = 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads',
			$uploadURL,
			$uploadPath,
			$fileToUpload,
			$uploadResponse,
			$responseStatus;
			
	private $credentials = array('service' => 'youtube', 'source' => 'youtubeuploader'),
			$developerKey = ''; 

	private $userHost = 'http://gdata.youtube.com/feeds/api/users/default/uploads';

	private $assignableCategories,
			$currentCategory,

			$title,
			$description,
			$keywords;

	private $fp;

	/**
	 * Constructor
	 * @param string $credentialsFile name of credentials file
	 */
	public function __construct($credentialsFile) {
		if(file_exists($credentialsFile) && $xml = simplexml_load_file($credentialsFile)) {

			$creds = $xml->children();

			foreach($creds as $c) {
				$name = $c->getName();
				switch ($name) {
					case 'developer_key':
						$this->developerKey = (string) $c;
						break;
					case 'email':
					case 'passwd':
						$this->credentials[ucfirst($name)] = (string) $c;
						break;
					default:
						$this->credentials[$name] = (string) $c;
				}
			}
			$url = parse_url($this->authHost);
			$this->authURL = $url['host'];
			$this->authPath = $url['path'];
	
			$url = parse_url($this->uploadHost);
			$this->uploadURL = $url['host'];
			$this->uploadPath = $url['path'];
		}
		else {
			throw new UploaderException("Credentials file '$credentialsFile' not found.");
		}
	}

	/**
	 * request ClientLogin token with provided credentials
	 */
	public function getAuth() {

		$curlHander = curl_init();

		curl_setopt_array($curlHander, array(
			CURLOPT_URL => $this->authHost,
			CURLOPT_PORT => 443,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => http_build_query($this->credentials, '', '&')
		));
		
		$result = curl_exec($curlHander);
		$status = curl_getinfo($curlHander, CURLINFO_HTTP_CODE);
		curl_close($curlHander);

		if($status < 200 || $status >= 300) {
			throw new UploaderException('getAuth() failed!');
		}		

		$lines = explode("\n", $result);

		foreach($lines as $l) {
			if(!empty($l)) {
				$tokens = explode('=', trim($l));
				if($tokens[0] === 'Auth') {
					$this->auth = $tokens[1];
				}
				else if($tokens[0] === 'YouTubeUser') {
					$this->youtubeUser = $tokens[1];
				}
			}
		}
		$this->userHost = str_replace('default', $this->youtubeUser, $this->userHost);
		$this->uploadHost = str_replace('default', $this->youtubeUser, $this->uploadHost);
	}

	/**
	 * uploads file to youtube server with requested token
	 * @param string $path path to file
	 * @param string $name optional name of clip, defaults to filename
	 */
	public function uploadFile($path, $name = NULL) {
		if(!file_exists($path)) {
			throw new UploaderException("uploadFile() failed! File '$path' not found.");
		}

		$this->fileToUpload = $path;
		$filename = !empty($name) ? $name : pathinfo($this->fileToUpload, PATHINFO_BASENAME);
		$boundary = uniqid();

		$curlHandler = curl_init();

		curl_setopt_array($curlHandler, array(
			CURLOPT_URL => $this->uploadHost,
			CURLOPT_POST => true,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				"Authorization: GoogleLogin auth={$this->auth}",
				"X-GData-Key: key={$this->developerKey}",
				"X-GData-Client: youtubeUploader",
				"GData-Version: 2",
				"Slug: $filename",
				"Content-Type: multipart/related; boundary='$boundary'",
				"Connection: close"
			),
			CURLOPT_POSTFIELDS => implode("\n", array(
				"--$boundary",
				"Content-Type: application/atom+xml; charset=UTF-8\n",
				'<?xml version="1.0"?>',
				'<entry	xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"	xmlns:yt="http://gdata.youtube.com/schemas/2007">',
				"<media:group>",
				"<media:title type='plain'>{$this->title}</media:title>",
				"<media:description type='plain'>{$this->description}</media:description>",
				"<media:category scheme='http://gdata.youtube.com/schemas/2007/categories.cat'>{$this->currentCategory}</media:category>",
				"<media:keywords>{$this->keywords}</media:keywords>",
				'</media:group>',
				'</entry>',			
				"--$boundary",
				"Content-Type: application/octet-stream",
				"Content-Transfer-Encoding: binary\n",
				file_get_contents($this->fileToUpload),
				"--$boundary--"))
		));

		$this->uploadResponse	= simplexml_load_string(curl_exec($curlHandler));
		$this->responseStatus	= curl_getinfo($curlHandler);
		
		curl_close($curlHandler);
	}

	/**
	 * returns XML server response
	 * $with $asString true, response is returned as string,
	 * otherwise as SimpleXML object
	 * @param boolean $asString
	 */
	public function getUploadResponse($asString = FALSE) {
		if($asString) {
			return $this->uploadResponse->asXML();
		}
		return $this->uploadResponse;
	}

	/**
	 * sets title information of clip
	 * filters illegal characters and trims length to allowed size
	 * @param string $title
	 */
	public function setTitle($title) {
		$title = preg_replace('~[\x00-\x1f<>]~mis', ' ', mb_substr($title, 0, 100, 'UTF-8'));

		if(trim($title) == '') {
			throw new UploaderException('setTitle() failed! No valid title specified.');
		}

		$this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	/**
	 * sets description of clip
	 * filters illegal characters and trims length to allowed size
	 * @param string $description
	 */
	public function setDescription($description) {
		$description = preg_replace('~[\x00-\x1f<>]~mis', ' ', $description);
		if(trim($description) == '') {
			throw new UploaderException('setDescription() failed! No valid description specified.');
		}

		$this->description = $description;
	}

	public function getDescription() {
		return $this->description;
	}

	/**
	 * sets keywords of clip
	 * filters illegal characters removes too short keywords,
	 * trims keywords and overall length to allowed size
	 * @param string|array $keywords
	 */
	public function setKeywords($keywords) {
		if(!is_array($keywords)) {
			$keywords = explode(',', $keywords);
		}

		$filtered = array();
		$sumLen = 0;

		foreach($keywords as $k) {
			$k = preg_replace('~[\x00-\x20<>]~mis', '', $k);
			if(mb_strlen(trim($k), 'UTF-8') <= 2) {
				continue;
			}
			else {
				$cropped = mb_substr($k, 0, 25, 'UTF-8');
				$sumLen += strlen($cropped);
				if($sumLen > 120) {
					break;
				}
				$sumLen++;
				$filtered[] = $cropped;
			}
		}

		if(empty($filtered)) {
			throw new UploaderException('setKeywords() failed! No valid keywords specified.');
		}

		$this->keywords = implode(',', $filtered);
	}

	public function getKeywords() {
		return $this->keywords;
	}

	/**
	 * sets clip category
	 * category is checked against Google server for validity
	 * @param string $cat
	 */
	public function setCategory($cat) {
		if(!isset($this->assignableCategories)) {
			$this->getAllowedCategories();
		}
		if(empty($this->assignableCategories) || !array_key_exists($cat, $this->assignableCategories)) {
			return FALSE;
		}
		$this->currentCategory = $cat;
		return $this->assignableCategories[$cat];
	}

	/**
	 * provide
	 * all valid categories provided by Google server
	 * returned array has valid category terms as keys and labels as values 
	 * @return $categories
	 */
	public function getAssignableCategories() {
		if(!isset($this->assignableCategories)) {

			$xml = simplexml_load_file('http://gdata.youtube.com/schemas/2007/categories.cat');
	
			if($xml === FALSE) {
				throw new UploaderException('getAllowedCategories() failed! Category file not found or not valid XML.');
			}
			
			$ns = $xml->getDocNamespaces(true);
	
			$assignableCategories = $xml->xpath('atom:category/yt:assignable/parent::*');
	
			$this->assignableCategories = array();
	
			foreach($assignableCategories as $cat) {
				$attr = $cat->attributes(); 
				$this->assignableCategories[(string) $attr->term] = (string) $attr->label;
			}
		}

		return $this->assignableCategories;
	}

	/**
	 * retrieves all videos of current user
	 * @return array of SimpleXML entries
	 */
	public function getUserVideos() {
		$curlHandler = curl_init();

		curl_setopt_array($curlHandler, array(
			CURLOPT_URL => $this->userHost,
			CURLOPT_HTTPHEADER => array(
				"Authorization: GoogleLogin auth={$this->auth}",
				"X-GData-Key: key={$this->developerKey}",
				"X-GData-Client: youtubeUploader",
				"GData-Version: 2"),
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true
		));

		$xml = simplexml_load_string(curl_exec($curlHandler));
		$this->responseStatus = curl_getinfo($curlHandler);

		curl_close($curlHandler);

		$result = array();
		foreach($xml->entry as $x) {
			$result[] = $x;
		}

		return $result;
	}
	
	/**
	 * update video
	 * @param string $id
	 */
	public function updateVideo($id) {
		$curlHandler = curl_init();

		curl_setopt_array($curlHandler, array(
			CURLOPT_URL => "{$this->userHost}/$id",
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/atom+xml",
				"Authorization: GoogleLogin auth={$this->auth}",
				"X-GData-Key: key={$this->developerKey}",
				"GData-Version: 2"),
			CURLOPT_POSTFIELDS => implode("\n", array(
				'<?xml version="1.0"?>',
				'<entry	xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"	xmlns:yt="http://gdata.youtube.com/schemas/2007">',
				"<media:group>",
				"<media:title type='plain'>{$this->title}</media:title>",
				"<media:description type='plain'>{$this->description}</media:description>",
				"<media:category scheme='http://gdata.youtube.com/schemas/2007/categories.cat'>{$this->currentCategory}</media:category>",
				"<media:keywords>{$this->keywords}</media:keywords>",
				'</media:group>',
				'</entry>')),			
			CURLOPT_RETURNTRANSFER => true
		));

		curl_exec($curlHandler);
		$this->responseStatus = curl_getinfo($curlHandler);

		curl_close($curlHandler);
	}

	/**
	 * delete video
	 * @param string $id
	 */
	public function deleteVideo($id) {
		$curlHandler = curl_init();

		curl_setopt_array($curlHandler, array(
			CURLOPT_URL => "{$this->userHost}/$id",
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/atom+xml",
				"Authorization: GoogleLogin auth={$this->auth}",
				"X-GData-Key: key={$this->developerKey}",
				"GData-Version: 2"),
			CURLOPT_RETURNTRANSFER => true
		));

		curl_exec($curlHandler);
		$this->responseStatus = curl_getinfo($curlHandler);
		
		curl_close($curlHandler);
	}
	
	/**
	 * retrieve data of single video
	 * requires only the video id, not the complete URI
	 * @param string $id
	 * @return $simplexml
	 */
	public function getVideoData($id) {
		$curlHandler = curl_init();

		curl_setopt_array($curlHandler, array(
			CURLOPT_URL => "http://gdata.youtube.com/feeds/api/videos/$id",
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true
		));

		$xml = simplexml_load_string(curl_exec($curlHandler));
		curl_close($curlHandler);

		return $xml;
	}
	
	/**
	 * get response status of last curl request
	 */
	public function getResponseStatus() {
		return $this->responseStatus;
	}
}