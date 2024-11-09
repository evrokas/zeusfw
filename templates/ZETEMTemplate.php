<?php

/* Zeus Template System
 *
 * Code from url: https://codeshack.io/lightweight-template-engine-php/
 * Thanks to David Adams
 * 
 * Used with modifications by Evangelos Rokas (c) 2024
 * major modification [Apr, 2024] template_path changed to array
 *   so to allow for more folders to be searched for templates
 */

class Renderer {
	static $blocks = array();
	static $template_path = array();
	static $cache_path = 'cache/';
	static $cache_enabled = FALSE;
	// static $cache_enabled = true;   //FALSE;
	static $enable_comments = TRUE;
	// static $enable_comments = FALSE;
	static $template_files = array();

	// $template_path is the path for the template files
	static function init($template_path) {
		// $template_path can be string or array of strings, but self::$template_path
		// should be an array
		global $kernel;

		// if config['enable_template_cache'] is false, then do not _set_ $cache_path,
		// so the code that follows takes that into account
		if($kernel->getConfig('enable_template_cache')) {
			self::$cache_enabled = true;

			if($kernel->safeGetConfig('template_cache_path') != '') {
				self::$cache_path = $kernel->getConfig('template_cache_path');
			}
		} 

		$enable_comments = $kernel->getConfig('enable_template_comments');
		
		if(isset($template_path)) {
			if(is_array($template_path))self::$template_path = $template_path;
			else array_push(self::$template_path, $template_path);
		}

		// if(isset($cache_path)) {
		// 	if(is_bool((boolval($cache_path))) {
		// 		if(!$cache_path)self::$cache_enabled = false;
		// 	} else {
		// 		self::$cache_path = $cache_path;
		// 		self::$cache_enabled = true;
		// 	}
		// }

		if(isset($enable_comments))self::$enable_comments = $enable_comments;

		// echo self::emmitComment("Template path: ".print_r(self::$template_path,1) . PHP_EOL .
		// "Cache path: " . self::$cache_path . PHP_EOL . 
		// "Comments : " . self::$enable_comments);

		self::$template_files = array();
		foreach(self::$template_path as $tpath) {
			// add templates to template_files
			self::findTemplates($tpath, self::$template_files);
		}
	}
	static function view($file, $data = array(), $stemplate = null) {
		$cached_file = self::cache($file, $stemplate);
	    extract($data, EXTR_SKIP);
	   	require $cached_file;
	}

	static function render($file, $data = array(), $stemplates = null) {
		ob_start();
		$cached_file = self::cache($file, $stemplates);
		extract($data, EXTR_SKIP);
		require $cached_file;
		
		$buffer = ob_get_contents();
		ob_end_clean();
		
		return $buffer;
	}

	
	// render a template only if the corresponding template exists, otherwise
	// return 404 content
	static function renderSafe($file, $data = array(), $stemplated = null) {
		if(self::existsTemplate($file))
			return self::render($file, $data, $stemplate);
		else
			return error_404();
	}

	/* return true if template file exists, false otherwise */
	static function existsTemplate($tname) {
		if(array_key_exists( $tname, self::$template_files)) {
			return true;
		} else return false;
	}
	
	/* return recursively all templates in $apth */
	static function findTemplates($apath, &$farr) {
		// $files = glob($apath . '*.zetem');

		// $dir  = new RecursiveDirectoryIterator($apath, RecursiveDirectoryIterator::SKIP_DOTS);
		// $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($apath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY);

		// print_r( $files );
		// $farr = array();
		// echo "Template files in path: $apath\n";

		$dupl=0;
		foreach($files as $fnam) {
			$f = explode('/', $fnam);
			// $farr[  str_replace('.zetem', '', $f[ array_key_last($f) ]) ] = $fnam;
			if(isset( $farr [ $f[ array_key_last($f)] ])) {
				// echo "Override template name exists. (". $f[ array_key_last($f)]. " @ " . $fnam->getPathName() . ")\n";
				// echo "Duplicate template name exists. (". $f[ array_key_last($f)]. " @ " . $fnam->getPathName() . ")\n";
				$dupl++;
				// exit;
			}
			$farr[ $f[ array_key_last($f) ] ] = $fnam->getPathName();
		}
		// print_r( $farr );
		if($dupl>0) {
			// duplicate overrides parent template, so allow it!
			// echo "Please rename the above duplicates!\n";
			// exit;
		}
	//   return ( $farr );
	}

	static function getTemplateSuggestions($args, callable $callback, &$tsuggestions) {
		$callback($args, $tsuggestions);
	}
	
	static function getTemplate($suggestions) {
		// echo self::emmitComment('Template suggestions: ' . implode(' * ', $suggestions));
		foreach(array_reverse($suggestions) as $s) {
			// echopre("checking template $s");
			if(isset(self::$template_files[ $s.'.zetem' ]))return $s.'.zetem';
		}
	}

	static function cache($file, $stemplates) {
		if (!file_exists(self::$cache_path)) {
		  	mkdir(self::$cache_path, 0744);
		}
	    $cached_file = self::$cache_path . str_replace(array('/', '.zetem'), array('_', ''), $file . '.php');
		// echo "Searching for cached file : $cached_file\n";
		// echo "Template: ". self::$template_files[ $file ] . "\n";
		// echopre("Cache file: ". $cached_file . " modification date: " . filemtime($cached_file ));

	    if (!self::$cache_enabled || !file_exists($cached_file) || filemtime($cached_file) < filemtime(self::$template_files[ $file ])) {
			
			if($stemplates != null && self::$enable_comments) {
				$code = "<!--- template suggestions: ---!>";
				foreach($stemplates[0] as $sugg) {
					$code .= "<!--- ";

					if($sugg .'.zetem'== $stemplates[1])$code .= " * ";
					else $code .= " - ";
					
					$code .= $sugg.'.zetem' . " ---!>";
				}

				// $code = self::emmitComment("Template suggestions:\n" . implode("\n * ", $stemplates[0]));
			} else $code = '';
			// $code = self::emmitComment("processing $file");
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

	/* $anl == true, emmit PHP_EOL, other wise no */
	static function emmitComment($acomment, $acode = "", $anl = true) {
		if(!self::$enable_comments)return $acode;

		$acode = $acode . ($anl?PHP_EOL:'') . "<!-- " . $acomment . " -->" . ($anl?PHP_EOL:'');

	  return ($acode);
	}

	static function compileCode($code) {
		$code = self::compileComments($code);
		$code = self::compileBlock($code);
		$code = self::compileYield($code);
		$code = self::compileEscapedEchos($code);
		$code = self::compileEchos($code);
		$code = self::compilePHP($code);
		return $code;
	}

	static function includeFiles($file) {
		$code = self::emmitComment(" begin include file : $file from " . self::$template_files[ $file ] ." ", false);
		// $code .= file_get_contents(self::$template_path . $file);
		$code .= file_get_contents(self::$template_files[ $file ]);
	
		preg_match_all('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER);
		// echo "Process include files\n";
        foreach ($matches as $value) {
            // print_r( $value );
			$code = str_replace($value[0], self::includeFiles($value[2]), $code);
		}
		$code = preg_replace('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code);
		$code = self::emmitComment(" end include file : $file ", $code, false);

		return $code;
	}

	static function compileComments($code) {
	return preg_replace('~\{#\s*(.+?)\s*\#}~is', '', $code);
}

	static function compilePHP($code) {
		return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
	}

	static function compileEchos0($code) {
		return preg_replace('~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code);
	}
	static function compileEchos($code) {
		$ret = preg_replace_callback("~\{{\s*(.+?)\s*\}}~is",
			function ($matches) {

				$parts = preg_split('/\s*\|\s*(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $matches[1]);


				// $parts = array_map("trim", explode('|', $matches[1]));

				$mainContent = $parts[1]; // Content before `|`
		
				$filterPart = array_slice($parts, 1);

				$filterFuncs = array();
				if(count($filterPart)) {
					foreach($filterPart as $filter) {
						$filterFunc = preg_replace_callback(
							"~\b(\w+)\s*\(\s*([^,()]+(?:\s*,\s*[^,()]+)*)\s*\)~is",
							function ($matches) {
								// $matches[1] is the function name
								$functionName = $matches[1];
						
								// Split the arguments by commas and trim any whitespace around each
								$args = array_map('trim', explode(',', $matches[2]));
						
								// Format as a single array argument for the new function call
								// $argsArray = "['" . implode("', '", $args) . "']";
								$argsArray = "[" . implode(", ", $args) . "]";
						
								// Return the transformed function call
								return "template_filters['$functionName']($argsArray)";
							},
							$filter);
						$filterFuncs[] = $filterFunc;
					}
				}
				// // Join the captured parts for demonstration or further processing
				$additionalContent = implode(', ', $filterFuncs);
				// $tx = print_r($additionalParts, 1);
				$tx = print_r($additionalContent, 1);
				// // $extraContent = isset($matches[2]) ? $matches[2] : ''; // Content after `|`, if it exists
		
				// Replace logic using both `$mainContent` and `$extraContent`
				return "<?php echo $parts[0];	 /* $tx */ ?>";
			},
			$code
		);
		return $ret;
		return preg_replace('~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code);
	}

	static function compileEscapedEchos($code) {
		return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>', $code);
	}

	static function compileBlock($code) {
		preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER);
		// $code = self::emmitComment("including blocks", $code);
        foreach ($matches as $value) {
            // print_r( $value );
			if (!array_key_exists($value[1], self::$blocks)) self::$blocks[$value[1]] = '';
			if (strpos($value[2], '@parent') === false) {

				self::$blocks[$value[1]] = self::emmitComment("begin block1 $value[1]", null, false);
				self::$blocks[$value[1]] .= $value[2];
				self::$blocks[$value[1]] .= self::emmitComment("end block: $value[1]", null, false);
			} else {
				self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], self::emmitComment("begin block2: $value[1]") . $value[2] . self::emmitComment("end block: $value[1]", false));
			}
            // $code .= "/* include block: $value[0]" . PHP_EOL;
			$code = str_replace($value[0], '', $code);
		}
		return $code;
	}

	static function compileYield($code) {
		foreach(self::$blocks as $block => $value) {
			$code = preg_replace('/{% ?yield ?' . $block . ' ?%}/', 
			// self::emmitComment("begin yield", null, false) . $value . self::emmitComment("end yield", null, false),
			$value, 
			$code);
		}
		$code = preg_replace('/{% ?yield ?(.*?) ?%}/i', '', $code);
		return $code;
	}

}
