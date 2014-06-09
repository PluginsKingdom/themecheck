<?php
namespace ThemeCheck;
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link href='http://fonts.googleapis.com/css?family=Arimo&subset=latin' rel='stylesheet' type='text/css'>
		<style media="screen" type="text/css">
		html, body, div, span, a {
	margin: 0;
	padding: 0;
	border: 0;
	font-size: 100%;
	font: inherit;
	vertical-align: baseline;
}

a:link {text-decoration: none}
a:visited {text-decoration: none}
a:active {text-decoration: none}
a:hover {text-decoration: none; background-position: 100% 0;}

a {
	display:inline-block;
	border:none;
	font-family: 'Arimo', sans-serif;
}

.a40 {
	width:34px;
	height:40px;
}

.a80 {
	width:67px;
	height:80px;
}

.a240 {
	width:200px;
	height:240px;
}

a div{
	border:none;
	vertical-align: middle;
	text-align:center;
}

a .div40{
	font-size:17px;
	width:33px;
	height:40px;
	line-height:35px;
}

a .div80{
	font-size:34px;
	width:67px;
	height:80px;
	line-height:70px;
}

a .div240{
	font-size:100px;
	width:200px;
	height:240px;
	margin-top:0px;
	line-height:210px;
}
		</style>
	</head>
	<body style="font-family: 'Helvetica Neue', Helvetica, Arial, 'lucida grande', tahoma, verdana, arial, sans-serif;">
	<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-47860956-1', 'themecheck.org');
</script>
<?php
include_once 'include/FileValidator.php';
include_once 'include/shield.php';
$lang = 'en';
if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), $ExistingLangs)) $lang = strtolower($_GET['lang']);
$size = '80';
if (isset($_GET['size']) && $_GET['size']=="big") $size = '240';
if (isset($_GET['size']) && $_GET['size']=="small") $size = '40';

$hash = $_GET['id'];
if (preg_match("/^[a-zA-Z0-9]{25}$/", $hash)){
	$history = new History();
	$themeInfo = $history->loadThemeFromHash($hash);
	
	if (!empty($themeInfo))
	{
		?>
		<script>
		var loc = window.location.protocol + "//" + window.location.hostname + window.location.pathname + window.location.search;
		ga('send', 'pageview', loc, '<?php if ($themeInfo->themetype == 1 ) echo '[WP] '; if ($themeInfo->themetype == 2 ) echo '[Joomla] ';if ($themeInfo->themetype == 4 ) echo '[WP child] '; echo $themeInfo->namesanitized; ?>');
		</script>
		<?php
	
		$href = TC_HTTPDOMAIN.'/'.Route::getInstance()->assemble(array("lang"=>$lang, "phpfile"=>"results", "hash"=>$hash));
		
		displayShield($themeInfo, $lang, $size, $href, '');
		
	} else {
		?><script>
			ga('send', 'pageview');
		</script><?php
		echo __("Error : non existant id.", $lang);
	}
} else {
?><script>
			ga('send', 'pageview');
		</script><?php
echo __("Error : invalid id.", $lang);
}
?>
	</body>
</html>