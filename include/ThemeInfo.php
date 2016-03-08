<?php
namespace ThemeCheck;
require_once 'Bootstrap.php';
require_once TC_INCDIR.'/ListDirectoryFiles.php';
require_once TC_INCDIR.'/Check.php';
require_once TC_INCDIR.'/tc_helpers.php';
define("TC_LICENSE_NONE", 						0);
define("TC_LICENSE_CUSTOM", 					1);
define("TC_LICENSE_CREATIVE_COMMONS", 2);
define("TC_LICENSE_CC_BY", 						3);
define("TC_LICENSE_CC_BY_SA", 				4);
define("TC_LICENSE_CC_BY_ND", 				5);
define("TC_LICENSE_CC_BY_NC", 				6);
define("TC_LICENSE_CC_BY_NC_SA", 			7);
define("TC_LICENSE_CC_BY_NC_ND", 			8);
define("TC_LICENSE_MIT_X11", 					9);
define("TC_LICENSE_BSD", 							10);
define("TC_LICENSE_NEWBSD", 					11);
define("TC_LICENSE_FREEBSD", 					12);
define("TC_LICENSE_LGPL2", 						13);
define("TC_LICENSE_LGPL3", 						14);
define("TC_LICENSE_GPL2", 						15);
define("TC_LICENSE_GPL3",							16);
define("TC_LICENSE_APACHE", 					17);
define("TC_LICENSE_CDDL",							18);
define("TC_LICENSE_ECLIPSE", 					19);

/**
*		Store theme info and metadata
**/
class ThemeInfo
{
	public $themetype;// "joomla", "wordpress"
	public $hash; 	  // hash is the md5 file hash in base36
	public $hash_md5;
	public $hash_sha1;
	public $zipfilename; // the name of the original file
	public $zipmimetype;
	public $zipfilesize; // size of archive
	public $userIp;// ip of poster
	public $name;
	public $namesanitized;
	public $uriNameSeo;
	public $uriNameSeoHigherVersion;
	public $themedir; // directory name of installed theme in cms files
	public $author;
	public $description; // HTML description
	public $descriptionBB; // description encoded in BB-code
	public $themeUri;
	public $version;
	public $authorUri;
	public $authorMail;
	public $tags;
	public $layout; // 0 : undefined, 1 : fixed, 2 : fluid, 3 : responsive

	public $copyright;
	public $creationDate;
	public $modificationDate;
	public $validationDate;
	public $serializable;

	public $license;
	public $licenseUri;
	public $licenseText;
	public $cmsVersion;
	public $hasBacklinKey;
	public $filesIncluded;
	public $modulePositions;
	public $templateParameters;
	public $images;// thumbnails and snapshots
	public $parentName;
	public $parentId;
	public $validation_timestamp; // Unix timestamp
	public $isTemplateMonster;
	public $isThemeForest;	
	public $isCreativeMarket;
	public $isPiqpaq;
    public $isOpenSource;  
	public $isNsfw;

	public $themeroot; // not stored in db
	
	public function __construct($hash)
	{
		$this->hash = $hash;
		$this->serializable = true;
		$this->parentName = null;
		$this->parentId = null;
	}

	/**
	*		Analyze an unzipped theme and get metadata
	**/
	public function initFromUnzippedArchive($unzippath, $zipfilename, $zipmimetype, $zipfilesize)
	{
		$this->zipfilename = $zipfilename;
		$this->zipmimetype = $zipmimetype;
		$this->zipfilesize = $zipfilesize;
		$this->userIp = $_SERVER['REMOTE_ADDR'];
		if ($this->userIp == '::1') $this->userIp = '127.0.0.1';
		$this->layout = 0;
		$rawlicense = '';

		// list of meaningful files in themes
		// txt, csv, etc. are meaningless
		$filetypes = array(	'css'=>false,
												'php'=>false,
												'html'=>false,
												'phtml'=>false,
												'htm'=>false,
												'xml'=>false,
												'gif'=>false,
												'jpeg'=>false,
												'png'=>false,
												'psd'=>false,
												'ai'=>false,
												'doc'=>false,
												'docx'=>false,
												'rtf'=>false);
		try {
			$files = listdir( $unzippath );
		} catch (Exception $e) {
			UserMessage::enqueue(__("Cannot list files in archive."), ERRORLEVEL_FATAL);
			return false;
		}
		if ( empty($files) ) {
			UserMessage::enqueue(__("Cannot list files in archive."), ERRORLEVEL_FATAL);
			return false;
		}

		// test for nested zips
		$nestedzipfiles = array();
		$indexphp_count = 0;
		foreach( $files as $key => $filename ) {
			$path_parts = pathinfo($filename);
			if (isset($path_parts['extension']))
			{
				if ($path_parts['extension'] == 'zip') $nestedzipfiles[] = $filename;
				if ($path_parts['basename'] == 'index.php') {
				/*	$subdir = substr($path_parts['dirname'], strpos($path_parts['dirname'], '/unzip/') + 7);
					if (strpos($subdir,"\\")!==false || strpos($subdir,"/")!==false)
					{
						UserMessage::enqueue(__("index.php must be in root directory."), ERRORLEVEL_FATAL);
						return false;
					}*/
					$indexphp_count++;
				}
			}
		}

		if (count($nestedzipfiles)>0 && $indexphp_count==0)
		{
			$curpath = trim($unzippath,"\\/");
			if (strpos($curpath, '_tc_parentzip') !== false) // prevent infinite looping
			{
				UserMessage::enqueue(__("Multiple level nested zip archives are not supported."), ERRORLEVEL_FATAL);
				return false;
			}
			$newpath = $curpath.'_tc_parentzip';
			if(!file_exists($newpath)) rename($curpath, $newpath);
			if (!file_exists($newpath)) {
				UserMessage::enqueue(__("Nested zip archives are not supported."), ERRORLEVEL_FATAL);
				return false;
			}
			try {
				$zipfilepath = str_replace($curpath, $newpath, $nestedzipfiles[0]);
				$zip = new \ZipArchive();
				$unzippath = $curpath;
				$res = $zip->open($zipfilepath);
				if ($res === TRUE) {
					if (file_exists($unzippath)) ListDirectoryFiles::recursiveRemoveDir($unzippath); // needed to avoid keeping old files that don't exist anymore in the new archive
					$zip->extractTo($unzippath);
					$zip->close();
				} else {
					UserMessage::enqueue(__("File could not be unzipped."), ERRORLEVEL_FATAL);
				}
			} catch (Exception $e) {
				UserMessage::enqueue(__("Archive extraction failed. The following exception occured : ").$e->getMessage(), ERRORLEVEL_FATAL);
			}
			return $this->initFromUnzippedArchive($unzippath, $zipfilename, $zipmimetype, $zipfilesize); // only $unzippath has changed, other values still refer to parent zip
		}

		$this->themetype = $this->detectThemetype($unzippath);
		
        $merchant = self::getMerchant($unzippath, $this); 
		
		$this->isThemeForest = ($merchant == 'themeforest') ? true : false;
		$this->isTemplateMonster = ($merchant == 'templatemonster') ? true : false;
		$this->isCreativeMarket = ($merchant == 'creativemarket') ? true : false;
		$this->isPiqpaq = ($merchant == 'piqpaq') ? true : false;

		if($merchant == null) // if $merchant is null we may be open source
		{
			// Look for theme on Wordpress.org et Joomla24
			$isOpenSource = false;
			include_once('curl_requests.php');

			if($this->license != 0)
			{
				// Does theme exist on open source plateforms
				if ($this->themetype == TT_JOOMLA) $isOnOpenSourcePlatform = isOnJoomla24($this->name, $this->zipfilename);
				else $isOnOpenSourcePlatform = isOnWordpressOrg($this->themedir);
				
				if($isOnOpenSourcePlatform)
				{
					$this->isOpenSource = true;
				}
				else
				{
					$this->isOpenSource = null; // we don't know
				}
			}
			else
			{
				$this->isOpenSource = false;
			}                   
		}      
		else 
		{
		  $this->isOpenSource = false;
		}
            
		$this->themedir = '';

		// undefined theme type
		if ($this->themetype == TT_UNDEFINED)
		{
			UserMessage::enqueue(__("Archive is not a valid theme file."), ERRORLEVEL_FATAL);
			return false;
		}

		if ($this->themetype == TT_WORDPRESS || $this->themetype == TT_WORDPRESS_CHILD)
		{
			$style_css = null;
			// loop through files to find style.css (at root level since there can be other style.css files in subdirs)
			foreach( $files as $key => $filename ) {
				$path_parts = pathinfo($filename);
				$basename = $path_parts['basename'];
				if ($basename == 'style.css')
				{
					if (empty($style_css)) {
						$style_css = $filename;
						$this->themeroot = realpath($path_parts['dirname']);
						$explod = explode(DIRECTORY_SEPARATOR, $this->themeroot);
						if (count($explod) > 0)	$this->themedir = $explod[count($explod)-1];
					}
					else if (strlen($filename) < strlen($style_css)) {
						$style_css = $filename;
						$this->themeroot = realpath($path_parts['dirname']);
						$explod = explode(DIRECTORY_SEPARATOR, $this->themeroot);
						if (count($explod) > 0)	$this->themedir = $explod[count($explod)-1];
					}
				}

				if (isset($path_parts['extension'])) {
					$ext = strtolower(trim($path_parts['extension']));
					if (isset($filetypes[$ext])) $filetypes[$ext] = true;
				}
			}
			if (empty($style_css))
			{
				UserMessage::enqueue(__("style.css is missing or misspelled."), ERRORLEVEL_FATAL);
				return false;
			} else {
				$file_content = file_get_contents($style_css);

					if ( preg_match('/[ \t\/*#]*Theme Name:(.*)$/mi', 	$file_content, $match) && !empty($match) && count($match)==2) $this->name = trim($match[1]);
					else {
						UserMessage::enqueue(__("style.css does not contain Theme name. Theme name is mandatory."), ERRORLEVEL_FATAL);
						return false;
					}
					if ( preg_match('/[ \t\/*#]*Description:(.*)$/mi', 	$file_content, $match) && !empty($match) && count($match)==2){
                        $this->descriptionBB = trim(strip_tags(Helpers::encodeBB($match[1])));
                        $this->description = Helpers::decodeBB($this->descriptionBB);
                    }
					if ( preg_match('/[ \t\/*#]*Author:(.*)$/mi', 			$file_content, $match) && !empty($match) && count($match)==2) $this->author = trim($match[1]);
					if ( preg_match('/[ \t\/*#]*Theme URI:(.*)$/mi', 		$file_content, $match) && !empty($match) && count($match)==2) $this->themeUri = trim($match[1]);
					if ( preg_match('/[ \t\/*#]*Author URI:(.*)$/mi', 	$file_content, $match) && !empty($match) && count($match)==2) $this->authorUri = trim($match[1]);
					if ( preg_match('/[ \t\/*#]*Version:(.*)$/mi', 			$file_content, $match) && !empty($match) && count($match)==2) $this->version = trim($match[1]);
					if ( preg_match('/[ \t\/*#]*License:(.*)$/mi', 			$file_content, $match) && !empty($match) && count($match)==2) $rawlicense = trim($match[1]);
					if ( preg_match('%[ \t\/*#]*License URI:.*(https?://[A-Za-z0-9-\./_~:?#@!$&\'()*+,;=]*)%mi', 	$file_content, $match) && !empty($match) && count($match)==2) $this->licenseUri = trim($match[1]);
					if ( preg_match('/[ \t\/*#]*Tags:(.*)$/mi', 				$file_content, $match) && !empty($match) && count($match)==2) 
					{
						$this->tags = trim($match[1]);
						
						// layout
						if (strpos($this->tags, 'fixed-layout') !== false) $this->layout = 1;
						if (strpos($this->tags, 'fluid-layout') !== false) $this->layout = 2;
						if (strpos($this->tags, 'responsive-layout') !== false) $this->layout = 3;
					}
					
					if ($this->themetype == TT_WORDPRESS_CHILD && preg_match('/[ \t\/*#]*Template:(.*)$/mi', 		$file_content, $match) && !empty($match) && count($match)==2) $this->parentName = trim($match[1]);
					// creation date can come from an external source. Happens with massimport where creation date is in csv files.
					global $g_creationDate;
					$this->creationDate = $g_creationDate;
			}
			$this->cmsVersion = LAST_WP_VERSION;
		}

		if ($this->themetype == TT_JOOMLA)
		{
			foreach( $files as $key => $filename ) {
				$path_parts = pathinfo($filename);
				$basename = $path_parts['basename'];
				if ($basename == 'templateDetails.xml')
				{
						libxml_use_internal_errors(true);
						$xml = simplexml_load_file($filename);
						if (count(libxml_get_errors()) > 0 )
						{
							foreach (libxml_get_errors() as $error) {
									$message = __("Malformed xml file templateDetails.xml. Cannot continue. Error details : ");
									$message .= $error->message;
									UserMessage::enqueue($message, ERRORLEVEL_FATAL);
							}
							return false;
							libxml_clear_errors();
						}

						if (empty($xml)) {
							UserMessage::enqueue(__("templateDetails.xml is empty or contains malformed xml"), ERRORLEVEL_FATAL);
							return false;
						}

						if ($xml->getName() == 'extension' || $xml->getName() == 'install' || $xml->getName() == 'mosinstall')
						{
							if(!empty($xml->name)) $this->name = (string)$xml->name;
							else {
								UserMessage::enqueue(__("templateDetails.xml does not have a name tag. name is mandatory."), ERRORLEVEL_FATAL);
								return false;
							}
							if(!empty($xml->description)){
																$this->descriptionBB = trim(strip_tags(Helpers::encodeBB((string)$xml->description)));
																$this->description = Helpers::decodeBB($this->descriptionBB);
                            }
							if(!empty($xml->author)) $this->author = (string)$xml->author;
							if(!empty($xml->authorUrl)) $this->authorUri = (string)$xml->authorUri;
							if(!empty($xml->authorMail)) $this->authorUri = (string)$xml->authorMail;
							if(!empty($xml->version)) $this->version = (string)$xml->version;
							if(!empty($xml->copyright)) $this->copyright = (string)$xml->copyright;
							if(!empty($xml->creationDate)) $this->creationDate = strtotime((string)$xml->creationDate);
							if (empty($this->creationDate)) {
								// creation date can come from an external source. Happens with massimport where creation date is in csv files.
								global $g_creationDate;
								$this->creationDate = $g_creationDate;
							}
							if(!empty($xml->license)) {
								$rawlicense = (string)$xml->license;
							}

							if ($xml->getName() == 'extension') {
								$attrs = $xml->attributes();
								if (isset($attrs["version"])) $this->cmsVersion = (string)$attrs["version"];
								else $this->cmsVersion = "3.0+";
							}
							if ($xml->getName() == 'install') {
								$attrs = $xml->attributes();
								if (isset($attrs["version"])) $this->cmsVersion = (string)$attrs["version"];
								else $this->cmsVersion = "1.5";
							}
							if ($xml->getName() == 'mosinstall') $this->cmsVersion = "1.0";
							
							// layout
							if (!empty($this->description)) {
								if (stripos($this->description, 'fluid') !== false) $this->layout = 2;
								if (stripos($this->description, 'responsive') !== false) $this->layout = 3;
							}
						
						}  else {
							UserMessage::enqueue(__("templateDetails.xml does not have a mosinstall, extension or install or node"), ERRORLEVEL_FATAL);
							return false;
						}
						
						$this->themeroot = realpath($path_parts['dirname']);
				}
				if (isset($path_parts['extension'])) {
					$ext = strtolower(trim($path_parts['extension']));
					if (isset($filetypes[$ext])) $filetypes[$ext] = true;
				}
			}
			if (empty($this->name))
			{
				UserMessage::enqueue(___("templateDetails.xml is emissing or misspelled."), ERRORLEVEL_FATAL);
				return false;
			}
			
			// for joomla, extract themedir from zip name
			$zipfilename_exploded = explode('.', $this->zipfilename);
			array_pop($zipfilename_exploded);
			$this->themedir = implode('.', $zipfilename_exploded );
		}
		
		$this->validationDate = time();
		$this->license = self::getLicense($rawlicense);
		
		// check URIs. Invalid URIs are changed to null
		if ($this->license == TC_LICENSE_CUSTOM) {
			$this->licenseText = $rawlicense;
			
			if (preg_match('%(https?://[A-Za-z0-9-\./_~:?#@!$&\'()*+,;=])%i', $rawlicense, $match) && !empty($match) && count($match)==2) // if contains an url
			{
				$this->licenseUri = trim($match[1]);
			}
			if (!empty($this->licenseUri) && !self::urlExists($this->licenseUri)) $this->licenseUri = null; // check 404s and other http errors
		}
		else $this->licenseUri = self::getLicenseUri($this->license);
		
		if (!empty($this->themeUri) && !(preg_match('%(https?://[A-Za-z0-9-\./_~:?#@!$&\'()*+,;=])%i', $this->themeUri) && self::urlExists($this->themeUri))) $this->themeUri = null; // check 404s and other http errors
		if (!empty($this->authorUri) && !(preg_match('%(https?://[A-Za-z0-9-\./_~:?#@!$&\'()*+,;=])%i', $this->authorUri) && self::urlExists($this->authorUri))) $this->authorUri = null; // check 404s and other http errors
		
		$this->hasBacklinKey = false;
		
		$this->filesIncluded = '';
		if ($filetypes['css']) $this->filesIncluded .= 'CSS, ';
		if ($filetypes['php']) $this->filesIncluded .= 'PHP, ';
		if ($filetypes['html'] || $filetypes['phtml'] || $filetypes['htm']) $this->filesIncluded .= 'HTML, ';
		if ($filetypes['xml']) $this->filesIncluded .= 'XML, ';
		if ($filetypes['gif'] || $filetypes['jpeg'] || $filetypes['png']) $this->filesIncluded .= 'Bitmap images, ';
		if ($filetypes['psd']) $this->filesIncluded .= 'Adobe Photoshop, ';
		if ($filetypes['xml']) $this->filesIncluded .= 'Adobe Illustrator, ';
		if ($filetypes['doc'] || $filetypes['docx']) $this->filesIncluded .= 'MS Word, ';
		if ($filetypes['rtf']) $this->filesIncluded .= 'RTF, ';

		$this->filesIncluded = trim($this->filesIncluded, " ,");
		
		// get themeforest url and check if really exists on themeforest
		if ($this->isThemeForest)
		{
				
			preg_match('/[ 0-9a-zA-Z_-]+/', $this->name, $matches, PREG_OFFSET_CAPTURE);
			$search = trim($matches[0][0]);
			$url = 'http://marketplace.envato.com/api/edge/search:themeforest,wordpress,'.$search.'.json';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$result = curl_exec($ch);
			curl_close($ch);
			$result = json_decode($result);
			if (!empty($result) && !empty($result->search))
			{
				$bestindex = -1;
				$bestsales = 0;
				$i = 0;
				foreach($result->search as $v)
				{
					if (intval($v->sales) > $bestsales) {
						$bestindex = $i;
						$bestsales = intval($v->sales);
					}
					$i++;
				}
				if ($bestindex >=0) $this->themeUri = $result->search[0]->url;
				//else $this->isThemeForest = false;
			} else {
				//$this->isThemeForest = false;
			}
		}
		
		// adult ?
		$this->isNsfw = self::isNsfw($unzippath, $this);

		return true;
	}

	/**
	*		Auto detect theme type
	**/
	static public function detectThemetype($unzippath)
	{
		$files = listdir( $unzippath );

		$score_wordpress = 0;
		$score_joomla = 0;

		$hasparenttemplate = false;
		$stylecss = false;
		$indexphp = false;
		$templateDetails = false;
		if ( $files ) {
			foreach( $files as $key => $filename ) {
				$path_parts = pathinfo($filename);
				$basename = $path_parts['basename'];
				if ($basename == 'style.css')
				{
					$file_content = file_get_contents($filename);

					if ( preg_match('/[ \t\/*#]*Theme Name:/i', $file_content) | preg_match('/[ \t\/*#]*Description:/i', $file_content) | preg_match('/[ \t\/*#]*Author:/i', $file_content) )
					{
						$score_wordpress ++;
					}
					if ( preg_match('/[ \t\/*#]*Template/i', $file_content) )
					{
						$hasparenttemplate = true;
					}
					$stylecss = true;
				}
				if ($basename == 'screenshot.png') $score_wordpress ++;
				if ($basename == 'screenshot.jpg') $score_wordpress ++;
				if ($basename == 'templateDetails.xml') {$score_joomla ++;$templateDetails = true;}
				if ($basename == 'templatedetails.xml') UserMessage::enqueue(__("Invalid joomla template. templatedetails.xml found. Please rename it templateDetails.xml (capital D)"), ERRORLEVEL_FATAL);
				if ($basename == 'template_thumbnail.png') $score_joomla ++;
				if ($basename == 'template_preview.png') $score_joomla ++;
				if ($basename == 'index.php')
				{
					$file_content = file_get_contents($filename);
					if ( preg_match('/get_header\s?\(\s?\)/', $file_content) && preg_match('/get_footer\s?\(\s?\)/', $file_content) ) $score_wordpress ++;
					if ( strpos($file_content, "'_JEXEC'") !== false) $score_joomla ++;
					$indexphp = true;
				}
			}
		}

		if ($score_joomla > $score_wordpress && $templateDetails) return TT_JOOMLA;
		if ($score_joomla < $score_wordpress && $stylecss) {
			if (!$indexphp && $hasparenttemplate) return TT_WORDPRESS_CHILD;
			return TT_WORDPRESS;
		}
		return TT_UNDEFINED;
	}
	
	/** 
	*		Is theme from a market place such as themeforest or template monster ?
	**/
	static public function getMerchant($unzippath, $themeInfo)
	{
		$is_themeforest = false;
		$is_templatemonster = false;
		$is_creativemarket = false;
		$zipname = $themeInfo->zipfilename;
		
		//if (strpos($zipname,'envato')!== false || strpos($zipname,'themeforest')!== false || strpos($zipname,'theme_forest')!== false) $is_themeforest = true;
		//else 
		if (strpos($themeInfo->themeUri,'creativemarket') !== false || strpos($themeInfo->authorUri,'creativemarket') !== false) $is_creativemarket = true;
		else if (strpos($zipname,'templatemonster')!== false || strpos($zipname,'template_monster')!== false || strpos($zipname,'template monster')!== false) $is_templatemonster = true;
		else 
		{
			$files = listdir( $unzippath );
			if ( $files ) {
				foreach( $files as $key => $filename ) {
				
					//if ( substr( $filename, -4 ) == '.php' || substr( $filename, -4 ) == '.css' || substr( $filename, -4 ) == '.txt' )
					if ( substr( $filename, -9 ) == "style.css" || substr( $filename, -19 ) == "templateDetails.xml")
					{
						$s = file_get_contents( $filename );
						//if (strpos($s,'envato') !== false || strpos($s,'themeforest') !== false || strpos($s,'theme forest') !== false) $is_themeforest = true;
						//else 
						if (strpos($s,'creativemarket')!== false || strpos($s,'creative_market')!== false || strpos($s,'creative market')!== false) $is_creativemarket = true;
						else if (strpos($s,'templatemonster')!== false || strpos($s,'template_monster')!== false || strpos($s,'template monster')!== false) $is_templatemonster = true;
					}
					
					if ($is_themeforest || $is_templatemonster || $is_creativemarket) break;
				}
			}
		}

		if (defined('ENVATO_KEY'))
		{ 
			$sanit_name = self::sanitizedString($themeInfo->name);

			$authorization = "Authorization: Bearer ".ENVATO_KEY;
			$url = 'https://api.envato.com/v1/discovery/search/search/item?term='.$sanit_name.'&site=themeforest.net&category=wordpress';
			$handle = curl_init($url);
			curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false); // nothing sensitive, it's okay
			$result = curl_exec($handle);
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if(intval($httpCode) == 200) {
				$json = json_decode($result);
				
				if (!empty($json->matches[0]->url) && strpos($json->matches[0]->url,'/item/'.$sanit_name) !== false) 
				{
					$is_themeforest = true;
				}
			}
			curl_close($handle);
		}

		if (strpos(strtolower($themeInfo->name),'themekiller')) return null;
		if ($is_themeforest) return 'themeforest';
		else if ($is_templatemonster) return 'templatemonster';
		else if ($is_creativemarket) return 'creativemarket';
		else return null;
	}
	
        
	static public function isNsfw($unzippath, $themeInfo)
	{
		$isNsfw = false;
		$zipname = strtolower($themeInfo->zipfilename);
		
		if (strpos($zipname,'xvideo')!== false || 
			strpos($zipname,'flat_tube')!== false || 
			strpos($zipname,'wpxtube')!== false || 
			strpos($zipname,'tubeace')!== false || 
			strpos($zipname,'bangy')!== false ||
			strpos($zipname,'adult')!== false || 
			strpos($zipname,'porn')!== false || 
			strpos($zipname,'xxx')!== false || 
			strpos($zipname,'sex')!== false) $isNsfw = true;

		else 
		{
			$files = listdir( $unzippath );
			if ( $files ) {
				foreach( $files as $key => $filename ) {
				
					//if ( substr( $filename, -4 ) == '.php' || substr( $filename, -4 ) == '.css' || substr( $filename, -4 ) == '.txt' )
					if ( substr( $filename, -9 ) == "style.css" || substr( $filename, -19 ) == "templateDetails.xml")
					{
						$s = file_get_contents( $filename );
						if (strpos($s,'porn') !== false || strpos($s,'adult') !== false || strpos($s,'xxx') !== false) $is_themeforest = true;
					}
					
					if ($isNsfw) break;
				}
			}
		}

		return $isNsfw;
	}

	/*
	*		Gets the report path of an item from its hash.
	**/
	static public function getReportDirectory($hash)
	{
		$path = TC_VAULTDIR.'/reports';

		// split directory tree to avoid huge directories that are so slow in FTP
		$path1 = substr($hash, 0, 2);
		$path2 = substr($hash, 2, 2);
		$path3 = substr($hash, 4);
		$path = $path.'/'.$path1.'/'.$path2.'/'.$path3;

		return $path;
	}

	/**
	*		Gets the public path of an item from its hash.
	**/
	static public function getPublicDirectory($hash)
	{
		$path = TC_ROOTDIR.'/dyn';

		// split directory tree to avoid huge directories that are so slow in FTP
		$path1 = substr($hash, 0, 2);
		$path2 = substr($hash, 2, 2);
		$path3 = substr($hash, 4);
		$path = $path.'/'.$path1.'/'.$path2.'/'.$path3;

		return $path;
	}

	/**
	*		Test license recognition width different patterns
	**/
	static public function testLicence()
	{
		$t = array('creative commons' => TC_LICENSE_CREATIVE_COMMONS,
							 'creative-commons' => TC_LICENSE_CREATIVE_COMMONS,
							 'ygyg creative-commons' => TC_LICENSE_CREATIVE_COMMONS,
							 'ygyg Creative-commons efce' => TC_LICENSE_CREATIVE_COMMONS,
							 'ygyg creativecommons efce' => TC_LICENSE_CREATIVE_COMMONS,
							 'CC-BY' => TC_LICENSE_CC_BY,
							 'CC_BY' => TC_LICENSE_CC_BY,
							 'CC BY' => TC_LICENSE_CC_BY,
							 'http://creativecommons.org/licenses/by/1.0/' => TC_LICENSE_CC_BY,
							 'Creative-commons BY' => TC_LICENSE_CC_BY,
							 'CC-BY-SA' => TC_LICENSE_CC_BY_SA,
							 'CC_BY-SA' => TC_LICENSE_CC_BY_SA,
							 'CC BY SA' => TC_LICENSE_CC_BY_SA,
							 'http://creativecommons.org/licenses/by-sa/2.0/' => TC_LICENSE_CC_BY_SA,
							 'Creative-commons BY SA' => TC_LICENSE_CC_BY_SA,
							 'CC-BY-NC-SA' => TC_LICENSE_CC_BY_NC_SA,
							 'CC_BY-NC-SA' => TC_LICENSE_CC_BY_NC_SA,
							 'CC BY NC SA' => TC_LICENSE_CC_BY_NC_SA,
							 'Creative-commons BY NC SA' => TC_LICENSE_CC_BY_NC_SA,
							 'CC-BY-NC-ND' => TC_LICENSE_CC_BY_NC_ND,
							 'CC_BY-NC-ND' => TC_LICENSE_CC_BY_NC_ND,
							 'CC BY NC ND' => TC_LICENSE_CC_BY_NC_ND,
							 'Creative-commons BY NC ND' => TC_LICENSE_CC_BY_NC_ND,
							 'CC-BY-NC' => TC_LICENSE_CC_BY_NC,
							 'CC_BY-NC' => TC_LICENSE_CC_BY_NC,
							 'CC BY NC' => TC_LICENSE_CC_BY_NC,
							 'Creative-commons BY NC' => TC_LICENSE_CC_BY_NC,
							 'CC-BY-ND' => TC_LICENSE_CC_BY_ND,
							 'MIT' => TC_LICENSE_MIT_X11,
							 'X11' => TC_LICENSE_MIT_X11,
							 'MIT X11' => TC_LICENSE_MIT_X11,
							 'MIT_X11' => TC_LICENSE_MIT_X11,
							 'X11-MIT' => TC_LICENSE_MIT_X11,
							 'http://opensource.org/licenses/MIT' => TC_LICENSE_MIT_X11,
							 'BSD' => TC_LICENSE_BSD,
							 'blabla BSD' => TC_LICENSE_BSD,
							 'New BSD' => TC_LICENSE_NEWBSD,
							 'revised BSD' => TC_LICENSE_NEWBSD,
							 'revised_BSD' => TC_LICENSE_NEWBSD,
							 'revised BSD 3' => TC_LICENSE_NEWBSD,
							 'BSD 3.0' => TC_LICENSE_NEWBSD,
							 'Free BSD' => TC_LICENSE_FREEBSD,
							 'simplified BSD' => TC_LICENSE_FREEBSD,
							 'simplified_BSD' => TC_LICENSE_FREEBSD,
							 'simplified BSD 2' => TC_LICENSE_FREEBSD,
							 'BSD 2.0' => TC_LICENSE_FREEBSD,
							 'GPL' => TC_LICENSE_GPL2,
							 'GNUGPL' => TC_LICENSE_GPL2,
							 'http://opensource.org/licenses/GPL-2.0' => TC_LICENSE_GPL2,
							 'LGPL' => TC_LICENSE_LGPL2,
							 'LGPL 3' => TC_LICENSE_LGPL3,
							 'LGPL-2' => TC_LICENSE_LGPL2,
							 'GNU-LGPL' => TC_LICENSE_LGPL2,
							 'GNU LGPL' => TC_LICENSE_LGPL2,
							 'gnu_lgpl' => TC_LICENSE_LGPL2,
							 'GNU' => TC_LICENSE_GPL2,
							 'general public license' => TC_LICENSE_GPL2,
							 'GNU LGPL 2' => TC_LICENSE_LGPL2,
							 'Apache' => TC_LICENSE_APACHE,
							 'cddl' => TC_LICENSE_CDDL,
							 'COMMON DEVELOPMENT AND DISTRIBUTION LICENSE' => TC_LICENSE_CDDL,
							 'eclipse' => TC_LICENSE_ECLIPSE,
							 'epl-1.0' => TC_LICENSE_ECLIPSE,
							 'my own license' => TC_LICENSE_CUSTOM,
							 '' => TC_LICENSE_NONE,
							);
		foreach ($t as $k => $v)
		{
			$a = self::getLicense($k);
			if ($a == $v) echo 'OK';
			else echo '--';
			echo '&nbsp;'.self::getLicenseName($a).' -> '.$k.'<br>';
		}
	}

	/**
	*		Search a string for a regular license
	**/
	static public function getLicense($rawString)
	{
		$ret = TC_LICENSE_CUSTOM;
		if (empty($rawString)) $ret = TC_LICENSE_NONE;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY[-_ \t]?NC[-_ \t]?SA\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY_NC_SA;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY[-_ \t]?NC[-_ \t]?ND\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY_NC_ND;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY[-_ \t]?SA\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY_SA;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY[-_ \t]?NC\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY_NC;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY[-_ \t]?ND\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY_ND;
		else if ( preg_match('/\b(CC|Creative[-_ \t]?Common[s]?).*BY\b/i', $rawString, $match)) $ret = TC_LICENSE_CC_BY;
		else if ( preg_match('/\bCreative[-_ \t]?Common[s]?\b/i', $rawString, $match)) $ret = TC_LICENSE_CREATIVE_COMMONS;
		else if ( preg_match('/((\bMIT\b)|(\bX11\b))|(MIT_X11)|(X11_MIT)/i', $rawString, $match)) $ret = TC_LICENSE_MIT_X11;
		else if ( preg_match('/((new|revised)[-_ \t]?BSD)|(BSD.*3)/i', $rawString, $match)) $ret = TC_LICENSE_NEWBSD;
		else if ( preg_match('/((simplified|free)[-_ \t]?BSD)|(BSD.*2)/i', $rawString, $match)) $ret = TC_LICENSE_FREEBSD;
		else if ( preg_match('/BSD/i', $rawString, $match)) $ret = TC_LICENSE_BSD;
		else if ( preg_match('/(LGPL.*3)|(lesser general[-_ ]public[-_ ]licen(c|s)e.*3)/i', $rawString, $match)) $ret = TC_LICENSE_LGPL3;
		else if ( preg_match('/(LGPL.*2)|(lesser general[-_ ]public[-_ ]licen(c|s)e.*2)/i', $rawString, $match)) $ret = TC_LICENSE_LGPL2;
		else if ( preg_match('/(LGPL|lesser[-_ ]general[-_ ]public)/i', $rawString, $match)) $ret = TC_LICENSE_LGPL2;
		else if ( preg_match('/(GPL.*3)|(general[-_ ]public[-_ ]licen(c|s)e.*3)/i', $rawString, $match)) $ret = TC_LICENSE_GPL3;
		else if ( preg_match('/(GPL.*2)|(general[-_ ]public[-_ ]licen(c|s)e.*2)/i', $rawString, $match)) $ret = TC_LICENSE_GPL2;
		else if ( preg_match('/(GNU|GPL|General[-_ ]Public[-_ ]Licen(c|s)e)/i', $rawString, $match)) $ret = TC_LICENSE_GPL2;
		else if ( preg_match('/apache/i', $rawString, $match)) $ret = TC_LICENSE_APACHE;
		else if ( preg_match('/(CDDL|Common[-_ ]Development[-_ ]and[-_ ]Distribution)/i', $rawString, $match)) $ret = TC_LICENSE_CDDL;
		else if ( preg_match('/(epl|eclipse)/i', $rawString, $match)) $ret = TC_LICENSE_ECLIPSE;

		return $ret;
	}

	/**
	*		Return URL of a license from license id
	**/
	static public function getLicenseUri($licenseId)
	{
		$uris = array(TC_LICENSE_NONE 						=> '',
									TC_LICENSE_CUSTOM 					=> '',
									TC_LICENSE_CREATIVE_COMMONS => 'http://creativecommons.org/licenses/by/2.0/',
									TC_LICENSE_CC_BY 						=> 'http://creativecommons.org/licenses/by/2.0/',
									TC_LICENSE_CC_BY_SA 				=> 'http://creativecommons.org/licenses/by-sa/2.0/',
									TC_LICENSE_CC_BY_ND 				=> 'http://creativecommons.org/licenses/by-nd/2.0/',
									TC_LICENSE_CC_BY_NC 				=> 'http://creativecommons.org/licenses/by-nc/2.0/',
									TC_LICENSE_CC_BY_NC_SA 			=> 'http://creativecommons.org/licenses/by-nc-sa/2.0/',
									TC_LICENSE_CC_BY_NC_ND 			=> 'http://creativecommons.org/licenses/by-nc-nd/2.0/',
									TC_LICENSE_MIT_X11 					=> 'http://opensource.org/licenses/MIT',
									TC_LICENSE_BSD 							=> 'http://en.wikisource.org/wiki/BSD_License',
									TC_LICENSE_NEWBSD 					=> 'http://opensource.org/licenses/BSD-3-Clause',
									TC_LICENSE_FREEBSD 					=> 'http://opensource.org/licenses/BSD-2-Clause',
									TC_LICENSE_LGPL2 						=> 'http://opensource.org/licenses/LGPL-2.1',
									TC_LICENSE_LGPL3 						=> 'http://opensource.org/licenses/LGPL-3.0',
									TC_LICENSE_GPL2 						=> 'http://opensource.org/licenses/GPL-2.0',
									TC_LICENSE_GPL3 						=> 'http://opensource.org/licenses/GPL-3.0',
									TC_LICENSE_APACHE 					=> 'http://opensource.org/licenses/Apache-2.0',
									TC_LICENSE_CDDL 						=> 'http://opensource.org/licenses/CDDL-1.0',
									TC_LICENSE_ECLIPSE 					=> 'http://opensource.org/licenses/EPL-1.0');

		if (isset($uris[$licenseId])) return $uris[$licenseId];
		return '';
	}

	/**
	*		Return the name of a license from license id
	**/
	static public function getLicenseName($licenseId)
	{
		$names = array(TC_LICENSE_NONE 						=> __('None'),
									TC_LICENSE_CUSTOM 					=> __('Custom'),
									TC_LICENSE_CREATIVE_COMMONS => __('Creative Commons'),
									TC_LICENSE_CC_BY 						=> __('Creative Commons BY'),
									TC_LICENSE_CC_BY_SA 				=> __('Creative Commons BY SA'),
									TC_LICENSE_CC_BY_ND 				=> __('Creative Commons BY ND'),
									TC_LICENSE_CC_BY_NC 				=> __('Creative Commons BY NC'),
									TC_LICENSE_CC_BY_NC_SA 			=> __('Creative Commons BY NC SA'),
									TC_LICENSE_CC_BY_NC_ND 			=> __('Creative Commons BY NC ND'),
									TC_LICENSE_MIT_X11 					=> __('MIT X11'),
									TC_LICENSE_BSD 							=> __('BSD'),
									TC_LICENSE_NEWBSD 					=> __('New BSD'),
									TC_LICENSE_FREEBSD 					=> __('Free BSD'),
									TC_LICENSE_LGPL2 						=> __('GNU LGPL 2'),
									TC_LICENSE_LGPL3 						=> __('GNU LGPL 3'),
									TC_LICENSE_GPL2 						=> __('GNU GPL 2'),
									TC_LICENSE_GPL3 						=> __('GNU GPL 3'),
									TC_LICENSE_APACHE 					=> __('Apache'),
									TC_LICENSE_CDDL 						=> __('CDDL'),
									TC_LICENSE_ECLIPSE 					=> __('Eclipse'));

		if (isset($names[$licenseId])) return $names[$licenseId];
		return '';
	}
	
	static public function urlExists($url)
	{
		if (isset($_SESSION["urlExists"][$url])) return $_SESSION["urlExists"][$url];
		
		$ret = true;
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($handle, CURLOPT_HEADER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10); // we don't want to link to slow pages
		curl_setopt($handle, CURLOPT_TIMEOUT, 15); //timeout in seconds, we don't want to link to slow pages

		$response = curl_exec($handle);
	
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if(intval($httpCode) >= 400 || $httpCode == 0) { //0 for non http error : dns, etc.
			$ret = false;
		}

		curl_close($handle);
		
		$_SESSION["urlExists"][$url] = $ret;
		return $ret;
	}
	
	static function sanitizedString($str)
	{
		// convert accents to un-accented letters
		$unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
		$str = strtr( $str, $unwanted_array );

		// convert non alphanumeric chars to "-"	
		$result = preg_replace("/[^-a-zA-Z0-9]+/", "-", $str);
		$seoname = preg_replace('/\%/',' percentage',$str); 
		$seoname = preg_replace('/\@/',' at ',$seoname); 
		$seoname = preg_replace('/\&/',' and ',$seoname); 
		$seoname = preg_replace('/\*/','x',$seoname); 
		$seoname = preg_replace('/\s[\s]+/','-',$seoname);    // Strip off multiple spaces 
		$seoname = preg_replace('/[\s\W]+/','-',$seoname);    // Strip off spaces and non-alpha-numeric 
		$seoname = preg_replace('/[\-]+/','-',$seoname); // Strip off multiple '-'
		$seoname = preg_replace('/^[\-]+/','',$seoname); // Strip off the starting hyphens 
		$seoname = preg_replace('/[\-]+$/','',$seoname); // // Strip off the ending hyphens 
		$result = strtolower($seoname); 

		return $result;
	}
}