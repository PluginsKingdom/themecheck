<?php
namespace ThemeCheck;

class Helpers
{
	/***
	*	Converts byte size in php.ini format to plain integer format
	**/
	public static function returnBytes($val) 
	{
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
	}
}