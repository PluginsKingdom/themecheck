<?php

namespace ThemeCheck;

class NonPrintable_Checker extends CheckPart
{		
		public function doCheck($php_files, $php_files_filtered, $css_files, $other_files, $themeInfo)
    {
        $this->errorLevel = ERRORLEVEL_SUCCESS;
                
        foreach ( $php_files_filtered as $name => $content ) // use $php_files_filtered because special chars are authorized in comments : non latin languages...
        {
            // 09 = tab
            // 0A = line feed
            // 0D = new line
            if ( preg_match($this->code, $content, $matches ) )
            {
                $filename = tc_filename( $name );
                $non_print = utf8_encode(tc_preg( $this->code , $name ));
                $this->messages[] = __all('Non-printable characters were found in file <strong>%1$s</strong>. This is an indicator of potential errors in PHP code.%2$s', $filename, $non_print);
                $this->errorLevel = $this->threatLevel;
            }
        }
    }
}

class NonPrintable extends Check
{	
    protected function createChecks()
    {
			$this->title = __all("Non-printable characters");
			$this->checks = array(
						new NonPrintable_Checker('NONPRINTABLE', TT_COMMON, ERRORLEVEL_WARNING, __all('Presence of non-printable characters in PHP files')	, '/[\x00-\x08\x0B-\x0C\x0E-\x1F\x80-\xFF]/', 'ut_nonprintable.zip')
			);
    }
}
