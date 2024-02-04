<?php

/* Zeus Template System
 *
 * Code from url: https://codeshack.io/lightweight-template-engine-php/
 * Thanks to David Adams
 * 
 * Used with modifications by Evangelos Rokas (c) 2024
 */

class ZETEMTemplate {
	static $blocks = array();
	static $cache_path = 'cache/';
	static $cache_enabled = FALSE;
	// static $cache_enabled = true;   //FALSE;
	static $enable_comments = TRUE;
	// static $enable_comments = FALSE;

	static function view($file, $data = array()) {
		$cached_file = self::cache($file);
	    extract($data, EXTR_SKIP);
	   	require $cached_file;
	}

	static function cache($file) {
		if (!file_exists(self::$cache_path)) {
		  	mkdir(self::$cache_path, 0744);
		}
	    $cached_file = self::$cache_path . str_replace(array('/', '.zetem'), array('_', ''), $file . '.php');
	    if (!self::$cache_enabled || !file_exists($cached_file) || filemtime($cached_file) < filemtime($file)) {
			$code = self::emmitComment("processing $file");
			$code .= self::includeFiles($file);
			$code = self::compileCode($code);
	        file_put_contents($cached_file, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code);
	    }
		return $cached_file;
	}

	static function clearCache() {
		foreach(glob(self::$cache_path . '*') as $file) {
			unlink($file);
		}
	}

	static function emmitComment($acomment, $acode = null) {
		if(self::$enable_comments)return ($acode . PHP_EOL . "<!-- " . $acomment . " --!>" . PHP_EOL );
		else return ($acode);
	}

	static function compileCode($code) {
		$code = self::compileBlock($code);
		$code = self::compileYield($code);
		$code = self::compileEscapedEchos($code);
		$code = self::compileEchos($code);
		$code = self::compilePHP($code);
		return $code;
	}

	static function includeFiles($file) {
		$code = self::emmitComment("begin include file : $file");
		$code .= file_get_contents($file);
	
		preg_match_all('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER);
		// echo "Process include files\n";
        foreach ($matches as $value) {
            // print_r( $value );
			$code = str_replace($value[0], self::includeFiles($value[2]), $code);
		}
		$code = preg_replace('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code);
		$code = self::emmitComment("end include file : $file", $code);

		return $code;
	}

	static function compilePHP($code) {
		return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
	}

	static function compileEchos($code) {
		return preg_replace('~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code);
	}

	static function compileEscapedEchos($code) {
		return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>', $code);
	}

	static function compileBlock($code) {
		preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER);
		$code = self::emmitComment("including blocks", $code);
        foreach ($matches as $value) {
            // print_r( $value );
			if (!array_key_exists($value[1], self::$blocks)) self::$blocks[$value[1]] = '';
			if (strpos($value[2], '@parent') === false) {
				self::$blocks[$value[1]] = self::emmitComment("block : $value[2]") . $value[2];
			} else {
				self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], self::emmitComment("block: $value[2]").$value[2]);
			}
            // $code .= "/* include block: $value[0]" . PHP_EOL;
			$code = str_replace($value[0], '', $code);
		}
		return $code;
	}

	static function compileYield($code) {
		foreach(self::$blocks as $block => $value) {
			$code = preg_replace('/{% ?yield ?' . $block . ' ?%}/', $value, $code);
		}
		$code = preg_replace('/{% ?yield ?(.*?) ?%}/i', '', $code);
		return $code;
	}

}
