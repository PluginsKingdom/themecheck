<?php
namespace ThemeCheck;
require_once 'Bootstrap.php';
require_once TC_INCDIR.'/ListDirectoryFiles.php';
require_once TC_INCDIR.'/Check.php';
require_once TC_INCDIR.'/tc_helpers.php';
require_once TC_INCDIR.'/ThemeInfo.php';
require_once TC_INCDIR.'/ValidationResults.php';

function objectToArray($d) {
	if (is_object($d)) {
		// Gets the properties of the given object
		// with get_object_vars function
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		/*
		* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
		return array_map(__FUNCTION__, $d);
	}
	else {
		// Return array
		return $d;
	}
}
	
class FileValidator
{
	public $themeInfo; // type ThemeInfo
	public $checklist = array();
	public $phpfiles = array();
	public $cssfiles = array();
	public $otherfiles = array();
					
	public $validationResults = array();// associative array of $lang => ValidationResult
	private $history = null; 

	private $checklistCommon = array (
            "Badthings",
            "Directories",
            "File",
            "Iframes",
            "Malware",
            "NonPrintable",
            "PHPShort",
            "Worms",
						"MandatoryFiles",
						"LineEndings",
						"AdminMenu",
						"Artisteer",
						"Basic",
						"CommentPagination",
						"CommentReply",
						"Constants",
						"ContentWidth",
						"Custom",
						"Deprecated",
						"MoreDeprecated", 
						"EditorStyle",
						"Gravatar",
						"I18NCheck",
						"Includes",
						"NavMenu",
						"PostFormat",
						"PostNav",
						"PostThumb",
						"SearchForm",
						"Style",
					//	"Suggested", //no : not sure this test makes sense
						"Tags",
					//	"TextDomain", //no : not sure this test makes sense 
					  "TimeDate",
						"Screenshot",
						"JManifest"
	);

	public function __construct($themeInfo)
	{
		$this->themeInfo = $themeInfo;
		if (USE_DB) $this->history = new History();
	}
	
	/** 
	*		Gets the upload path of an item from its hash.
	**/
	static public function hashToPathUpload($hash)
	{
		$path = TC_VAULTDIR.'/upload';
		if (!file_exists($path)) trigger_error('Directory TC_VAULTDIR/upload does not exist', E_USER_ERROR);
		
		$fullname = $path.'/'.$hash.'.zip';
		return $fullname;
	}
		
	/** 
	*		Save check results in a JSON file.
	**/
	public function serialize()
	{
		if($this->themeInfo == null) return false;	
		
		// save thumbnail
		$imgfile = '';
		foreach ($this->otherfiles as $fullpath=>$content)
		{
			$path_parts = pathinfo($fullpath);
			$basename = $path_parts['basename'];
			
			if ($this->themeInfo->themetype == TT_WORDPRESS && $basename == "screenshot.png") $imgfile = $fullpath;
			if ($this->themeInfo->themetype == TT_JOOMLA && $basename == "template_thumbnail.png") $imgfile = $fullpath;
		}

		if (!empty($imgfile ))
		{
			list($width_src, $height_src) = getimagesize($imgfile);
			$width = 206;
			$height = intval(($height_src * $width) / $width_src);
			$image_p = imagecreatetruecolor($width,$height);
			$image_src = imagecreatefrompng($imgfile);
			imagecopyresampled($image_p, $image_src, 0, 0, 0, 0, $width, $height, $width_src, $height_src); // resample and copy image. Since the image is shown on page results, resample even if same size, to avoid potential hacks.
			// 1 : save for front-end display (even if not serializable since we want to display a thumbnail on the results page)
			$savedirectory_img = ThemeInfo::getPublicDirectory($this->themeInfo->hash);
			if (!file_exists($savedirectory_img)) mkdir($savedirectory_img, 0774, true);
			imagepng($image_p, $savedirectory_img.'/thumbnail.png');
			// 2 : save the same pic in the vault
			if ($this->themeInfo->serializable)
			{
				$savedirectory_img = ThemeInfo::getReportDirectory($this->themeInfo->hash);
				if (!file_exists($savedirectory_img)) mkdir($savedirectory_img, 0774, true);
				imagepng($image_p, $savedirectory_img.'/thumbnail.png');
			}
		}
		
		// if theme is not serializable (duplicate theme name from different users, etc.)
		if (!$this->themeInfo->serializable) return false;
		
		$this->themeInfo->imagePath = realpath($savedirectory_img.'/thumbnail.png');

		// save meta data
		if (USE_DB) {
			$this->history->saveTheme($this->themeInfo);			
		}
		
		// save validation results
		foreach($this->validationResults as $lang=>$_validationResults)
		{
			$_validationResults->serialize($this->themeInfo->hash);
		}
	}
	
	/** 
	*		Restore check results from a JSON file.
	**/
	static public function unserialize($hash)
	{
		if (!USE_DB) return null;
		
		$directory = ThemeInfo::getReportDirectory($hash);
		if (!file_exists($directory )) return null;
		
		$history = new History();
		$themeInfo = $history->loadThemeFromHash($hash);
		if (empty($themeInfo)) return null;

		$fileValidator = new FileValidator($themeInfo);
		
		global $ExistingLangs;
		foreach($ExistingLangs as $l)
		{
			$_validationResults = ValidationResults::unserialize($hash, $l);
			if (empty($_validationResults)) continue;
			$fileValidator->validationResults[$l] = $_validationResults;
		}
			
		return $fileValidator;
	}
	
	/** 
	*		Upload an archive and return a ThemeInfo object
	**/
	static public function upload()
	{
		if (count($_FILES)==0 || !isset($_FILES["file"]) || !isset($_FILES["file"]["name"]) ) 
		{
			trigger_error(__("No files to upload"), E_USER_ERROR);
			return 0;
		}

		$accepted_exts = array("zip");
		$accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/x-compressed', 'application/octet-stream');
		$filetype = strtolower($_FILES["file"]["type"]);
		$extension = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));

		// check file type
		$filetype_ok = false;
		foreach($accepted_types as $mime_type) {
			if($mime_type == $filetype) {
				$filetype_ok = true;
				break;
			} 
		}
		if (!$filetype_ok)
		{
			if (empty($filetype)) $filetype = '_';
			trigger_error(sprintf(__("Bad file type. Mime type %s is not a recognized format for web themes. Uploaded file must be a zip archive."), $filetype), E_USER_ERROR);
			return 0;
		}
		
		// check file extension
		if (!in_array($extension, $accepted_exts))
		{
			trigger_error(sprintf(__("Bad file extension. File extension %s not recognized. File extension must be \".zip\"."), $_FILES["file"]["name"]), E_USER_ERROR);
			return 0;
		}
		
		if ($_FILES["file"]["error"] == UPLOAD_ERR_INI_SIZE || $_FILES["file"]["error"] == UPLOAD_ERR_FORM_SIZE)
		{
			trigger_error(sprintf(__("The uploaded file size exceeds %s."), ini_get('upload_max_filesize')), E_USER_ERROR);
			return 0;
		}
		
		if ($_FILES["file"]["error"] == UPLOAD_ERR_PARTIAL)
		{
			trigger_error(__("The uploaded file was only partially uploaded."), E_USER_ERROR);
			return 0;
		}
		
		if ($_FILES["file"]["error"] == UPLOAD_ERR_NO_FILE)
		{
			trigger_error(__("No file was uploaded."), E_USER_ERROR);
			return 0;
		}
		
		$src_path = $_FILES["file"]["tmp_name"];
		$src_name = $_FILES["file"]["name"];
		$src_type = $_FILES["file"]["type"];
		
		$themeInfo = self::prepareThemeInfo($src_path, $src_name, $src_type, true);
		return $themeInfo;
	}
	
	/** 
	*		Generate a hash, move or copy the archive and return a ThemeInfo object
	**/
	static public function prepareThemeInfo($src_path, $src_name, $src_type, $isUpload=false)
	{
		$src_size = filesize($src_path);
		if ($src_size < 1000)
		{
			$userMessage = UserMessage::getInstance();
			$userMessage->enqueueMessage(__('Files under 1 KB are not accepted. Operation canceled.'), ERRORLEVEL_ERROR);
			return null;
		}
		$hash_md5 = md5_file($src_path); 
		$sha1_file = sha1_file($src_path); 
		$hash_alpha = base_convert($hash_md5, 16, 36); // shorten hash to shorten urls (better looking, less bandwidth)
		
		while(strlen($hash_alpha) < 25) $hash_alpha = '0'.$hash_alpha;

		$zipfilepath = self::hashToPathUpload($hash_alpha);
		if ($isUpload)
			move_uploaded_file($src_path, $zipfilepath); // move file to final place (overwrites if already existing)
		else
			copy($src_path, $zipfilepath); // copy the file (overwrites if already existing)
		
		try {
			$zip = new \ZipArchive();
			$path = TC_ROOTDIR.'/../themecheck_vault/unzip';
			if (!file_exists($path)) trigger_error('Directory TC_ROOTDIR/../themecheck_vault/unzip does not exist', E_USER_ERROR);
			$unzippath = $path.'/'.$hash_alpha."/";
			
			$res = $zip->open($zipfilepath);
			if ($res === TRUE) {
			
				if (file_exists($unzippath)) ListDirectoryFiles::recursiveRemoveDir($unzippath); // needed to avoid keeped old files that don't exist anymore in the new archive
				$zip->extractTo($unzippath);

				$zip->close();
			} else {
				trigger_error(__("File could not be unzipped."), E_USER_ERROR);
			}
		} catch (Exception $e) {
			trigger_error(__("Archive extraction failed. The following exception occured : ").$e->getMessage(), E_USER_ERROR);
		}
		
		// create a theme info
		$themeInfo = new ThemeInfo($hash_alpha);
		$themeInfo->hash_md5 = $hash_md5;
		$themeInfo->hash_sha1 = $sha1_file;
		$themeInfo->initFromUnzippedArchive($unzippath, $src_name, $src_type, $src_size);
		return $themeInfo;	
	}
	
	/** 
	*		Dispatch files from an uncompressed archive in $this->phpfiles, $this->cssfiles or $this->otherfiles
	*		Used to prepare the call to validate()
	**/
	private function extractFiles($unzippedPath)
	{
		$files = listdir( $unzippedPath );
		/*$data = tc_get_theme_data( $theme . '/style.css' );
		if ( $data[ 'Template' ] ) {
			// This is a child theme, so we need to pull files from the parent, which HAS to be installed.
			$parent = get_theme_root( $data[ 'Template' ] ) . '/' . $data['Template'];
			if ( ! tc_get_theme_data( $parent . '/style.css' ) ) { // This should never happen but we will check while were here!
				echo '<h2>' . sprintf(___('Parent theme <strong>%1$s</strong> not found! You have to have parent AND child-theme installed!', 'themecheck'), $data[ 'Template' ] ) . '</h2>';
				return;
			}
			$parent_data = tc_get_theme_data( $parent . '/style.css' );
			$themename = basename( $parent );
			$files = array_merge( listdir( $parent ), $files );
		}*/

		if ( $files ) {
			foreach( $files as $key => $filename ) {
				if ( substr( $filename, -4 ) == '.php' ) {
					$this->phpfiles[$filename] = php_strip_whitespace( $filename );
				}
				else if ( substr( $filename, -4 ) == '.css' ) {
					$this->cssfiles[$filename] = file_get_contents( $filename );
				}
				else {
					// get all other files : txt, xml, jpg, png
					$sizelimit = 50000;
					$this->otherfiles[$filename] = ( ! is_dir($filename) ) ? file_get_contents( $filename, false, NULL, -1,  $sizelimit) : '';
				}
			}
		}
	}
			
	/** 
	*		Execute all checks 
	**/
	public function validate()
	{
		// prepare checks
		foreach ($this->checklistCommon as $check)
		{
			require_once(TC_INCDIR."/Checks/$check.php");
			$c = __NAMESPACE__.'\\'.$check;
			$this->checklist[] = new $c();
		}
		//prepare files
		$files = listdir( TC_ROOTDIR.'/../themecheck_vault/unzip/'.$this->themeInfo->hash );
		if ( $files ) {
			foreach( $files as $key => $filename ) {
				if ( substr( $filename, -4 ) == '.php' ) {
					$this->phpfiles[$filename] = php_strip_whitespace( $filename );
				}
				else if ( substr( $filename, -4 ) == '.css' ) {
					$this->cssfiles[$filename] = file_get_contents( $filename );
				}
				else {
					// get all other files : txt, xml, jpg, png
					$sizelimit = 50000;
					$this->otherfiles[$filename] = ( ! is_dir($filename) ) ? file_get_contents( $filename, false, NULL, -1,  $sizelimit) : '';
				}
			}
		}
		date_default_timezone_set('UTC');
		$this->themeInfo->validation_datetime = date("U"); // Unix timestamp
		
		$check_fails = array();
		$check_warnings = array();
		$check_successes = array();
		$check_undefined = array();
		$check_count = 0;
		$check_countOK = 0;
		$score = 0;
		
		// run validation. Checks are done in all existing languages and return multilingual arrays in place of strings.
		foreach ($this->checklist as $check)
		{
			$check->doCheck($this->phpfiles, $this->cssfiles, $this->otherfiles);
			foreach($check->checks as $checkpart)
			{
				//echo (get_class($check)).'<br>';
				if ($this->themeInfo->themetype & $checkpart->themetype) 
				{
					$checkpart->title = $check->title; // a bit dirty...
					if ($checkpart->errorLevel == ERRORLEVEL_ERROR) $check_fails[] = $checkpart;
					else if ($checkpart->errorLevel == ERRORLEVEL_WARNING) $check_warnings[] = $checkpart;
					else if ($checkpart->errorLevel == ERRORLEVEL_SUCCESS) $check_successes[] = $checkpart;
					else $check_undefined[] = $checkpart;
					
					$check_count++;
				}
			}
		}
		$this->themeInfo->check_count = $check_count;
		$this->themeInfo->check_countOK = count($check_successes) + count($check_warnings);
		if ($check_count > 0) $this->themeInfo->score = (100 * $this->themeInfo->check_countOK) / $this->themeInfo->check_count;
		else $this->themeInfo->score = 0.0;
		
		// generate validationResults, one for each existing language. Checks are monolingual : no more multilingual arrays.
		global $ExistingLangs;
		foreach($ExistingLangs as $l)
		{
			$this->validationResults[$l] = new ValidationResults($l);
			foreach($check_fails as $checkpart_multi) $this->validationResults[$l]->check_fails[] = $checkpart_multi->getMonolingual($l);
			foreach($check_warnings as $checkpart_multi) $this->validationResults[$l]->check_warnings[] = $checkpart_multi->getMonolingual($l);
			foreach($check_successes as $checkpart_multi) $this->validationResults[$l]->check_successes[] = $checkpart_multi->getMonolingual($l);
			foreach($check_undefined as $checkpart_multi) $this->validationResults[$l]->check_undefined[] = $checkpart_multi->getMonolingual($l);
		}		
	}
	
	public function getValidationResults($lang)
	{
		if (isset($this->validationResults[$lang])) return $this->validationResults[$lang];
		return $this->validationResults['en'];
	}
}
?>
