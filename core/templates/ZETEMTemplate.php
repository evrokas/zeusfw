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

if(!class_exists("Renderer")) {

class Renderer {
	static $comment_block = [
		"html" => ["start" => "<!--", "end" => "-->"],
		"php" => ['start' => "/*", "end" => "*/"],
		"js" => ["start" => "/*", "end" => "*/"],
	];

	static $blocks = array();
	static $template_path = array();
	static $cache_path = 'cache/';
	static $cache_enabled = FALSE;
	// static $cache_enabled = true;   //FALSE;
	static $enable_comments = TRUE;
	// static $enable_comments = FALSE;
	static $template_files = array();

	static $filterCallbackArray = array();	// filter callbacks

	static $cstart;
	static $cend;
	// $template_path is the path for the template files
	static function init($template_path, $enable_cache, $cache_path, $enable_comments = false, $cblock_type = 'html') {
		// $template_path can be string or array of strings, but self::$template_path
		// should be an array

		// if config['enable_template_cache'] is false, then do not _set_ $cache_path,
		// so the code that follows takes that into account
		if(!$enable_cache) {
			self::$cache_enabled = true;
			self::$cache_path = $cache_path;
		} else self::$cache_enabled = false;

		// $enable_comments = $kernel->getConfig('enable_template_comments');
		
		if(isset($template_path)) {
			if(is_array($template_path))self::$template_path = $template_path;
			else array_push(self::$template_path, $template_path);
		}

		if(isset($enable_comments))self::$enable_comments = $enable_comments;

		self::$cstart = self::$comment_block[ $cblock_type ]['start'];
		self::$cend = self::$comment_block[ $cblock_type ]['end'];
		// echo "Comment start " . self::$cstart;

		// echo self::emmitComment("Template path: ".print_r(self::$template_path,1) . PHP_EOL .
		// "Cache path: " . self::$cache_path . PHP_EOL . 
		// "Comments : " . self::$enable_comments);

		// self::$template_files = array();
		// foreach(self::$template_path as $tpath) {
		// 	// add templates to template_files
		// 	self::findTemplates($tpath, self::$template_files);
		// }
		self::scanTemplates();

		self::filterRegister('asset', 'Renderer::filter_asset');
		self::filterRegister('upper', 'Renderer::filter_uppercase');
		self::filterRegister('cache_asset', 'Renderer::filter_cache_asset');
		self::filterRegister('date', 'Renderer::filter_date');
		self::filterRegister('t', 'Renderer::filter_translate');

	}

	static function scanTemplates() {
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

	static function render($file, $data = array(), $stemplates = null): string
	{
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
	static function renderSafe($file, $data = array(), $stemplated = null): string
	{
		if(self::existsTemplate($file))
			return self::render($file, $data, $stemplated);
		else
			return error_404();
	}


	static function renderRaw($file, $data = array(), $stemplates = null): string
	{
		$comments = self::$enable_comments;
		self::$enable_comments = false;

		$result = self::render($file, $data, $stemplates);

		self::$enable_comments = $comments;

		return $result;
	}

	
	/* return true if template file exists, false otherwise */
	static function existsTemplate($tname) {
		// echopre(" **** checking for exist template: $tname");
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

		// echopre(print_r( $files, 1 ));
		// $farr = array();
		// echo "Template files in path: $apath\n";

		$dupl=0;
		foreach($files as $fnam) {
			$f = explode('/', $fnam);
			// $farr[  str_replace('.zetem', '', $f[ array_key_last($f) ]) ] = $fnam;
			if(isset( $farr [ $f[ array_key_last($f)] ])) {
				// echo "Override template name exists. (". $f[ array_key_last($f)]. " @ " . $fnam->getPathName() . ")\n";
				// echo Duplicate template name exists. (". $f[ array_key_last($f)]. " @ " . $fnam->getPathName() . ")\n";
				$dupl++;
				// exit;
			}
			// echopre("adding file " .  $f[ array_key_last($f)  ] . " [{$fnam->getPathName()}]");
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
	

	// create template suggestions from keywords in array $keyords, ie
	// $keywords = ['alpha', 'beta', 'gamma', 'delta'];
	// $prefix = 'template';
	// $templateSuggestions = getTemplateSuggestions($keywords, $prefix);
	// print_r($templateSuggestions);
	// Example Output with Prefix (template):
	// Array
	// (
	//     [0] => template--alpha--beta--gamma--delta
	//     [1] => template--alpha--beta--gamma
	//     [2] => template--alpha--beta--delta
	//     [3] => template--alpha--gamma--delta
	//     [4] => template--beta--gamma--delta
	//     [5] => template--alpha--beta
	//     [6] => template--alpha--gamma
	//     [7] => template--alpha--delta
	//     [8] => template--beta--gamma
	//     [9] => template--beta--delta
	//     [10] => template--gamma--delta
	//     [11] => template--alpha
	//     [12] => template--beta
	//     [13] => template--gamma
	//     [14] => template--delta
	// )

	static function getTemplateSuggestionsShuffle(array $keywords, string $prefix = ''): array {
		$suggestions = [];
		$count = count($keywords);
	
		// Generate all non-empty subsets
		for ($i = 1; $i < (1 << $count); $i++) {
			$subset = [];
			for ($j = 0; $j < $count; $j++) {
				if ($i & (1 << $j)) {
					$subset[] = $keywords[$j];
				}
			}
			$suggestion = implode('-', $subset);
			$suggestions[] = $prefix ? "{$prefix}--{$suggestion}" : $suggestion;
		}
	
		// Sort by number of elements descending, then lexicographically
		usort($suggestions, function ($a, $b) {
			$countA = substr_count($a, '-');
			$countB = substr_count($b, '-');
			return $countB <=> $countA ?: strcmp($a, $b);
		});
	
		return array_reverse($suggestions);
	}

	static function getTemplate($suggestions) {
		// echo self::emmitComment('Template suggestions: ' . implode(' * ', $suggestions));
		foreach(array_reverse($suggestions) as $s) {
			// echopre("checking template $s\n");
			if(array_key_exists("$s.zetem", self::$template_files)) {
				// echopre("found template $s\n");
				return "$s.zetem";
			}
		}
	}

	static function cache($file, $stemplates) {
		if (!file_exists(self::$cache_path)) {
		  	mkdir(self::$cache_path, 0744);
		}
	    $cached_file = self::$cache_path . str_replace(array('/', '.zetem'), array('_', ''), $file . '.php');

		// echopre(print_r(self::$template_files, 1));
		// echo "Searching for cached file : $cached_file\n";
		// echo "Template: ". self::$template_files[ $file ] . "\n";
		// echopre("Cache file: ". $cached_file . " modification date: " . filemtime($cached_file ));

	    if (!self::$cache_enabled || !file_exists($cached_file) || filemtime($cached_file) < filemtime(self::$template_files[ $file ])) {
			
			if($stemplates != null && self::$enable_comments) {
				// echopre("generating template from beginning, ". print_r($stemplates, 1));
				$code = self::$cstart . " template suggestions: " . self::$cend;
				foreach($stemplates[0] as $sugg) {
					$code .= self::$cstart;

					if($sugg .'.zetem'== $stemplates[1])$code .= " * ";
					else $code .= " - ";
					
					$code .= $sugg.'.zetem' . self::$cend;
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

		$acode = $acode . ($anl?PHP_EOL:'') . self::$cstart . $acomment . self::$cend . ($anl?PHP_EOL:'');

	  return ($acode);
	}

	static function compileCode($code) {
		$code = self::compileComments($code);
		$code = self::compileBlock($code);
		$code = self::compileYield($code);
		$code = self::compileEscapedEchos($code);
		$code = self::compileEchos($code);
		// $code = self::compilePHPplain($code);
		$code = self::compilePHP($code);
		return $code;
	}

	static function includeFiles($file) {
		global $kernel;

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
		$code = self::emmitComment(" end include file : $file", $code, true);

		return $code;
	}

	static function compileComments($code) {
	return preg_replace('~\{#\s*(.+?)\s*\#}~is', '', $code);
}

	static function compilePHP($code) {
		return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
	}
	static function compilePHPplain($code) {
		// echo "test";
		return preg_replace('~\{\!\%\s*(.+?)\s*\%\!\}~is', '$1', $code);
	}

	static function compileEchos0($code) {
		return preg_replace('~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code);
	}
	static function compileEchos($code) {
		// inside {{ }} there can be a number of options
		// logic added on Nov 2024, with the help of ChatGPT which provided the regex patterns,
		// and some of the underlying logic, heavily modified by Evangelos Rokas

		// cases:
		// {{ $variable }}
		// {{ $variable | raw }}
		// {{ $variable | t('gr') }}
		// {{ $variable | t | raw }}

		$ret = preg_replace_callback("~\{{\s*(.+?)\s*\}}~is",
			function ($matches0) {

				$parts = preg_split('/\s*\|\s*(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $matches0[1]);

				$mainContent = $parts[0]; // Content before `|`
		
				$filterPart = array_slice($parts, 1);

				$filterFuncs = array();
				foreach($filterPart as $filter) {
					$filterFunc = preg_replace_callback(
						// "~\b(\w+)\s*\(\s*([^,()]+(?:\s*,\s*[^,()]+)*)\s*\)~is",
						"~\b(\w+)\s*(\(([^,()]*?(?:\s*,\s*[^,()]*?)*)\))?~is",
						function ($matches) {
							// $matches[1] is the function name
							$functionName = $matches[1];
					
							// $matches[2] are the arguments, if exists
							if(isset($matches[2]) && $matches[3] !== '') {
								// Split the arguments by commas and trim any whitespace around each
								$args = array_map('trim', explode(',', $matches[3]));
						
								// Format as a single array argument for the new function call
								// $argsArray = "['" . implode("', '", $args) . "']";
								// $argsArray = "[" . implode(", ", $args) . "]";
							} else 
							// $argsArray = "[]";
							$args = array();

							// Return the transformed function call
							return serialize([
								"filter"=> $functionName, 
								"args" => $args ]);
								// "args" => serialize($argsArray) ]);
						},
						$filter);
					$filterFuncs[] = $filterFunc;
				}

				$tf = array();
				foreach($filterFuncs as $ff) {
					$tf[] = unserialize($ff);
				}
				$ttf = self::filterCallback($mainContent, $tf);
				// $tx = print_r($ttf, 1);
				$tx = $ttf;
				// Replace logic using both `$mainContent` and `$extraContent`
				// return "<?php echo $parts[0]; ";
				return "<?php echo $tx ?>";
			},
			$code
		);
		return $ret;
	}

	// call recursively filters in $filters in reverse order with initial input $token
	static function filterCallback(string $token, array $filters) {
		// $filters = array_reverse($filters);
		if(count($filters)>0) {
			$ret = '';
			foreach($filters as $filt) {
				$ret = 'call_user_func( self::$filterCallbackArray[ "'.$filt['filter'] . '" ], ' . $token ;
				//  . $token;
				if(count($filt['args'])>0)$ret .= ", " . 
												'[ ' . implode(", ", $filt['args'] ) . ' ]';
				// $filt['args'];	
				$ret .= " )";
				$token = $ret;
			}
		} else return $token;

		return $ret;
	}

	static function filter_asset($token, $args = array() ) {
		return asset( $token );
	}
	static function filter_cache_asset($token, $args = array() ) {
		$token = trim($token, '/ ', );
		return rel_url( '/cache/' . $token );
	}

	static function filter_uppercase($token, $args = array() ) {
		return strtoupper($token);
	}

	static function filter_date($token, $args = array() ) {
		// echopre("token : $token, args: " . print_r($args, 1));
		$dt = new DateTime($token);
		if(count($args)>0)return $dt->format($args[0]);
		else return $dt;
	}

	static function filter_translate($token, $args = array() ) {
		$out = t( $token );
		return $out;
	}

	static function filterRegister(string $name, string $cback) {
		self::$filterCallbackArray[ $name ] = $cback;
	}

	static function compileEscapedEchos0($code) {
		return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>', $code);
	}
	static function compileEscapedEchos($code) {
		return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo (string) $1 ?>', $code);
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

}	/* check for class_exist */