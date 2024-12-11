<?php

/* 
 * help functions
*/

// return the relative url to the item p, if it begins with http return
// the item as it is
function rel_url($p) {
    global $kernel;

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
    else if(is_array($at) && isset($at['attributes']))
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
    return (date("Y-m-d", strtotime($str)));
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

function randomAlnum($nchars, $nwords) {
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


function echopre($str) {
    echo "<pre>$str</pre>";
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

    $cnf = $kernel->getConfig();
    // echo "<pre>";print_r( $cnf['css'] );echo "</pre>";
    // echo "<pre>";print_r( $cnf['libraries'] );echo "</pre>";

    if(array_key_exists($libname, $cnf['libraries'])) {
        // echo "<pre>";print_r( $cnf['libraries'][$libname] );echo "</pre>";
        $kernel->addConfig($cnf['libraries'][$libname]);
    }

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
    $at = new Attributes("class", "index-token");
    return inject_block("span", $token, $at);

    // return "<span class=\"index\">".$token."</span>";
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

    // $file_dir = tempnam()
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
        echopre("Create folder: $output");
        $msk = umask();
        umask(0022);
        mkdir($output, 0777, true);
        umask($msk);
    }

    if($module) {
        $output .= '/' . $module;
    
        // test if module path exists
        if(!is_dir($output)) {
            echopre("Create folder: $output");
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