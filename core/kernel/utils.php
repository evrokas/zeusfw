<?php

/* 
 * help functions
*/

// return the relative url to the item p, if it begins with http return
// the item as it is
function rel_url($p) {
    global $kernel;

    if(!$p)return "";
    if(str_starts_with($p, 'http'))
        return ($p);

    return $kernel->rel_url($p);
}

// return the relative path of an item in assets/ folder
function asset($file) {
    return rel_url('assets/' . $file);
}

// return the attibute of an item,
// if item is of class Attributes then call the class getAttributes()
// an array and has key of 'aatribute'
function attributes($at) {
    if(is_object($at) && (get_class($at) === 'Attributes')) return $at->getAttributes();
    else if(is_array($at) && isset($at['attributes']) && (get_class($at['attributes']) === "Attributes"))
        return ($at['attributes']->getAttributes());
    else return '';
}

function guid() {
    return (trim(file_get_contents('/proc/sys/kernel/random/uuid')));
}

function getDBtime($atime = null) {
    if(!$atime)$atime=time();
    return (date ('Y-m-d H:i:s', $atime));  
}

function getDBformattime($str) {
    return (date("Y-m-d H:i:s", strtotime($str)));
}
function formatDateTime($str) {
    return (date("d-m-Y H:i:s", strtotime($str)));
}

function formatDate($str) {
    return (date("d-m-Y", strtotime($str)));
}

function formatBrowserDate($str) {
    if(is_string($str))
        return (date("Y-m-d", strtotime($str)));
    else if(is_int($str))
        return (date("Y-m-d", $str));
}

function randomChar($str) {
    $len = strlen($str);
    return ($str[ random_int(1, $len-1)]);
}

function randomAlpha($nchars, $nwords = 1) {
    static $alpha = 'abcdefghhjklmnopqrstuvwxyz';
    
    $words = array();
    while($nwords--) {
        $n = random_int(1, $nchars);
        $s = '';
        while($n>0) {
            $s .= randomChar($alpha);
            $n--;
        }
        $words[] = $s;
    }

    return(implode(' ', $words));
}

function randomNumber($ndigits) {
    static $numbers = '0123456789';

    $s = '';
    while($ndigits--) {
        $s .= randomChar($numbers);
    }

    return ($s);
}

function randomAlnum($nchars, $nwords=1) {
    static $alpha = 'abcdefghhjklmnopqrstuvwxyz0123456789ABCDEFGHJKLMNOPQRSTUVWXYZ';
    
    $words = array();
    while($nwords--) {
        $n = random_int(1, $nchars);
        $s = '';
        while($n--) {
            $s .= randomChar($alpha);
        }
        $words[] = $s;
    }

    return(implode(' ', $words));
}

function randomEmail() {
    $s = randomAlpha(1) . randomAlnum(10, 1) . '@' . randomAlnum(8, 1) . '.' . randomAlnum(2, 1);

    return ($s);
}

function logpre($str) {

}
function echopre($str, $tag = 'pre', $html = false) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);

    $fn = $caller['file'];
    $ln = $caller['line'];

    $fs = explode('/', $fn);
    $ap = explode('/', __APPDIR__);
    // $fn .= implode(':', $ap);

    while($fs[0] === $ap[0]) {
        array_shift($fs);
        array_shift($ap);
    }

    $fn = implode('/', $fs);


    if($html)$str = htmlspecialchars($str);
    echo "<$tag>"."$fn:$ln: " .$str."</$tag>";
    /* // echo "<textarea disabled="true"><?php echo htmlentities($str);?></textarea>";
     */
    // echo "<blockquote class='prefixed'><code><pre>".htmlentities(  $str ) . "<pre></code></blockquote>";
}
function echoprecode($str) {
    $str = htmlentities($str);
    echo "<pre><code>".$str."</code></pre>";
}


function module($astr, $params) {
    global $kernel;

    // echopre("Calling module: $astr");
    $mod = $kernel->getModule( $astr );
    // echopre("Found module: " . print_r($mod, 1));
    if(!$mod) {
        return (error_404());
    }
    return ( $mod->run( $params ) );
}

function remove_header_duplicates($list) {
    // echopre(print_r($list, 1));

    $final = array();
    foreach($list as $lk => $l) {
        $duplicate = 0;
        // echopre("scanning for: " . $l['src']);
        foreach($list as $rk => $r) {

            // test for items following the current $l item
            if(($rk > $lk) && ($r['src'] === $l['src'])) {
                // echopre("found duplicate: " . $r['src']);
                $duplicate++;
            }
        }
        // echopre("duplicate: $duplicate");
        
        if(!$duplicate)$final[] = $l;
    }
    // echopre("Final list: " . print_r($final, 1));
    return ($final);
}

function attach_library_helper($libname) {
    global $kernel;

    // error_log("attach library: " . $libname . "\n");
    // echopre("attach library: $libname");

    $cnf = $kernel->getConfig();
    // echopre( "pre-merged css: " . print_r($kernel->getConfig('css'), 1) );

    // echo "<pre>";print_r( $cnf['css'] );echo "</pre>";
    // echo "<pre>";print_r( $cnf['libraries'] );echo "</pre>";

    if(array_key_exists($libname, $cnf['libraries'])) {
        // echo "<pre>";print_r( $cnf['libraries'][$libname] );echo "</pre>";
        // echopre( print_r( $cnf['libraries'][$libname], 1));
        $kernel->addConfig($cnf['libraries'][$libname]);
    }

    // echopre( "merged css: " . print_r($kernel->getConfig('css'), 1) );

    // echo "<pre>";print_r( $kernel->getConfig('css'));echo "</pre>";
    // echo "<pre>";print_r( $kernel->getConfig('foot_script'));echo "</pre>";
    // exit();
}

function attach_library($libname) {
    if(is_array($libname)) {
        foreach($libname as $lib) {
            attach_library_helper( $lib );
        }
    } else {
        attach_library_helper( $libname );
    }
}

function recursive_array_replace ($find, $replace, $array) {
    if (!is_array($array)) {
        if(is_null($array))return null;
        else return str_replace($find, $replace, $array);
    }

    $newArray = [];
    foreach ($array as $key => $value) {
            $newArray[$key] = recursive_array_replace($find, $replace, $value);
    }
    return $newArray;
}

function recursive_array_search_key($needle_key, array $array)
{
    foreach ($array as $key => $value){
        if ($key === $needle_key) {
            return $value;
        }
        if (is_array($value)) {
            if (($result = recursive_array_search_key($needle_key,$value)) !== false) {
                return $result;
            }
        }
    }
    return false;
} 

function inject_block($blockname, $content, $attrs = null) {
    $s = "<$blockname";
    if($attrs)
        $s .= " " . attributes($attrs);
    $s .= ">";
    $s .= $content;
    $s .= "</$blockname>";

    return $s;
}

function kindex($token) {
    $at = new Attributes(["class" => "index-token"]);
    return inject_block("span", $token, $at);

    // return "<span class=\"index\">".$token."</span>";
}

// main translation function, translates $token to current language
// if $token is an array, then checks if the array has keys as the
// current language, if yes, then emits the value of that key
function t($token) {
    if(is_array($token)) {
        // it is an array, check to see if it has translation array keys
        global $kernel;
        $curLang = $kernel->getCurrentLanguage();
        if(array_key_exists($curLang, $token)) {
            return $token[ $curLang ];
        } else {
            return dictionaryClassEx::translateToken($token[ array_key_first($token) ]);
        }
    } else {
        return dictionaryClassEx::translateToken($token);
        // echopre("token $token => $word");
    }

    // return $word;
}

// if $tok is string, return that string,
// if $tok is an array, and current selected language is in array keys
//    then return value for that key, otherwise return value of first key
function getLangText($tok) {
  global $kernel;

    // echopre("token: " . print_r($tok, 1));
    if(is_array($tok)) {
        $lang = $kernel->getCurrentLanguage();
        if(key_exists($lang, $tok))return $tok[ $lang ];
        else return $tok[ array_key_first($tok) ];
    }

    if(is_string($tok))return $tok;

    return 'nolangtext';
}

function getConf($atok) {
  global $kernel;
 
      if(array_key_exists('config', $kernel->getConfig())) {
          if(array_key_exists($atok, $kernel->getConfig('config'))) {
              return $kernel->getConfig('config')[$atok];
          } else return null;
      } else return null;
}

function core_get_temp_filename(string $prefix) {
    global $kernel;

    $file_dir = tempnam($kernel->getConfig('template_cache_path'), $prefix);
    // echopre("Temporary file name: $file_dir");

    return $file_dir;
}

/**
 * string $filename     file name to return path to
 * string $module       prefix folder to add to file path
 * return               file path in lib path, prefixed with module
 */
function core_get_file_in_lib(string $filename, string $module = null) {
    global $kernel;

    // get library path from configuration
    $output = $kernel->getConfig('libpath');
    if(!$output) {
        echopre("ERROR: libpath is not set in configuration");
        $kernel->addStatus('error', 'KERNEL ERROR: libpath is not set in configuration');

        exit;
    }

    $output = trim($output);
    $output = trim($output, '/');

    $output =  __APPDIR__ . '/web/' . $output;


    // test if lib path exists
    if(!is_dir($output)) {
        $msk = umask();
        umask(0022);
        mkdir($output, 0777, true);
        umask($msk);
    }

    if($module) {
        $output .= '/' . $module;
    
        // test if module path exists
        if(!is_dir($output)) {
            $msk = umask();
            umask(0022);
            mkdir($output, 0777, true);
            umask($msk);
        }
    }

    // so, everything is in order
    $output .= '/' . $filename;

    // echopre("lib path: $output");

  return ($output);
}

/**
 * string $filename     file name to return path to
 * string $module       prefix folder to add to file path
 * return               file path in lib path, prefixed with module
 */
function core_get_dir_in_lib(string $module = null) {
    global $kernel;

    // get library path from configuration
    $output = $kernel->getConfig('libpath');
    if(!$output) {
        echopre("ERROR: libpath is not set in configuration");
        $kernel->addStatus('error', 'KERNEL ERROR: libpath is not set in configuration');

        exit;
    }

    $output = trim($output);
    $output = trim($output, '/');

    $output =  __APPDIR__ . '/web/' . $output;


    // test if lib path exists
    if(!is_dir($output)) {
        $msk = umask();
        umask(0022);
        mkdir($output, 0777, true);
        umask($msk);
    }

    if($module) {
        $output .= '/' . $module;
    
        // test if module path exists
        if(!is_dir($output)) {
            $msk = umask();
            umask(0022);
            mkdir($output, 0777, true);
            umask($msk);
        }
    }

    // echopre("lib path: $output");

  return ($output);
}


// https://stackoverflow.com/questions/6041741/fastest-way-to-check-if-a-string-is-json-in-php
function is_json($string) {
    json_decode($string);
  return json_last_error() === JSON_ERROR_NONE;
}

if(!function_exists('json_validate')) {
// more robust function
    function json_validate($string)
    {
        // decode the JSON data
        $result = json_decode($string);

        // switch and check possible JSON errors
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $error = ''; // JSON is valid // No error has occurred
                break;
            case JSON_ERROR_DEPTH:
                $error = 'The maximum stack depth has been exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Invalid or malformed JSON.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Control character error, possibly incorrectly encoded.';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON.';
                break;
            // PHP >= 5.3.3
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_RECURSION:
                $error = 'One or more recursive references in the value to be encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_INF_OR_NAN:
                $error = 'One or more NAN or INF values in the value to be encoded.';
                break;
            case JSON_ERROR_UNSUPPORTED_TYPE:
                $error = 'A value of a type that cannot be encoded was given.';
                break;
            default:
                $error = 'Unknown JSON error occured.';
                break;
        }

        if ($error !== '') {
            // throw the Exception or exit // or whatever :)
            exit($error);
        }

        // everything is OK
        return $result;
    }
}


function getDebugTrace() {
    $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
    $output = ["\nDebug backtrace:"];
    
    foreach ($trace as $index => $frame) {
        $file = $frame['file'] ?? '[internal]';
        $line = $frame['line'] ?? 0;
        $function = $frame['function'] ?? '';
        
        // Build function name with class/type if available
        $called = '';
        if (isset($frame['class'])) {
            $called = $frame['class'] . $frame['type'];
        }
        $called .= $function;

        // Format arguments
        $args = array_map(function ($arg) {
            return formatArgument($arg);
        }, $frame['args'] ?? []);

        $output[] = sprintf(
            "#%d %s(%d): %s(%s)",
            $index,
            basename($file),
            $line,
            $called,
            implode(', ', $args)
        );
    }

    // return '<pre>' . implode("\n", $output) . '</pre>';
    return implode("\n", $output);
}

function formatArgument($arg, $depth = 0, $maxDepth = 3) {
    if ($depth > $maxDepth) {
        return '...';
    }

    $type = gettype($arg);
    switch ($type) {
        case 'string':
            $short = strlen($arg) > 25 ? substr($arg, 0, 25) . '...' : $arg;
            return "'$short'";
            
        case 'array':
            $count = count($arg);
            if ($count === 0) return '[]';
            
            $items = [];
            foreach (array_slice($arg, 0, 5) as $key => $value) {
                $items[] = sprintf(
                    '%s => %s',
                    formatArgument($key, $depth + 1),
                    formatArgument($value, $depth + 1)
                );
            }
            if ($count > 5) $items[] = '...';
            return sprintf('array(%d) [%s]', $count, implode(', ', $items));
            
        case 'object':
            return 'object(' . get_class($arg) . ')';
            
        case 'resource':
            return 'resource(' . get_resource_type($arg) . ')';
            
        case 'boolean':
            return $arg ? 'true' : 'false';
            
        case 'NULL':
            return 'null';
            
        default: // integer, double, etc
            return (string)$arg;
    }
}

