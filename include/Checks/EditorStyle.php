<?php

namespace ThemeCheck;

class EditorStyle_Checker extends CheckPart
{	
		public function doCheck($php_files, $php_files_filtered, $css_files, $other_files)
    {
        $this->errorLevel = ERRORLEVEL_SUCCESS;
        $php = implode( ' ', $php_files );

        if ( strpos( $php, $this->code ) === false )
        {
            $this->messages[] =  __all('No reference to <strong>%1$s()</strong> was found in the theme. It is recommended that the theme implements editor styling, so as to make the editor content match the resulting post output in the theme, for a better user experience.', $this->code);
            $this->errorLevel = $this->threatLevel;
        }
    }
}

class EditorStyle extends Check
{	
    protected function createChecks()
    {
			$this->title = __all("Editor style");
			$this->checks = array(
						new EditorStyle_Checker('EDITORSTYLE', TT_WORDPRESS, ERRORLEVEL_WARNING, __all("Presence of editor style"), 'add_editor_style', 'ut_editorstyle.zip'),
			);
    }
}