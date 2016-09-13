<?php
namespace ThemeCheck;

class Cdn_Checker extends CheckPart
{	
	public function doCheck($php_files, $php_files_filtered, $css_files, $other_files, $themeInfo)
    {
        $this->errorLevel = ERRORLEVEL_SUCCESS;
		
        // combine all the php files into one string to make it easier to search
        $php_code = implode( ' ', $php_files );
        $css_code = implode( ' ', $css_files );
		$all_code = $php_code . ' ' . $css_code;
		
		foreach ( $this->code as $cdn_slug => $cdn_url)
		{
			if ( false !== strpos( $all_code, $cdn_url ) ) {
				$this->messages[] = __all( 'Found the URL of a CDN in the code: %s. CSS or Javascript resources should not be loaded from a CDN. These resources should be bundled with the theme.',  '<code>' . esc_html( $cdn_url ) . '</code>' );
				$this->errorLevel = $this->threatLevel;
				break;
			}
		}
    }
}

class Cdn extends Check
{	
    protected function createChecks()
    {
		$this->title = __all("Cdn");
		$this->checks = array(
					new Cdn_Checker('CDN', TT_COMMON, ERRORLEVEL_WARNING, __all("Use of CDN"), 
								array(
									'bootstrap-maxcdn'      => 'maxcdn.bootstrapcdn.com/bootstrap',
									'bootstrap-netdna'      => 'netdna.bootstrapcdn.com/bootstrap',
									'bootswatch-maxcdn'     => 'maxcdn.bootstrapcdn.com/bootswatch',
									'bootswatch-netdna'     => 'netdna.bootstrapcdn.com/bootswatch',
									'font-awesome-maxcdn'   => 'maxcdn.bootstrapcdn.com/font-awesome',
									'font-awesome-netdna'   => 'netdna.bootstrapcdn.com/font-awesome',
									'html5shiv-google'      => 'html5shiv.googlecode.com/svn/trunk/html5.js',
									'html5shiv-maxcdn'      => 'oss.maxcdn.com/libs/html5shiv',
									'jquery'                => 'code.jquery.com/jquery-',
									'respond-js'            => 'oss.maxcdn.com/libs/respond.js',
								) , 'ut_cdn.zip')
		);
    }
}