<?php

/*
 * Feeders php
 *
 * functions to render feed properly
 *   gathers all necessery info and finally renders the feed
 *   according to the values in the 'params' field
 * 
 * a complete description of the values of the 'params' field
 * needs to be written
 * 
 */

class Feeder {

    static function renderFeed(mixed $feed, array $params, string $routePath): string|null {
        $feedParams = json_decode($feed->getparams(), true);

        echopre("rendering Feed " . print_r($feed, 1) . " with params: " . print_r($params, 1) . " routePath: $routePath");
        foreach($feedParams as $key => $paramsData) {
            // transverse all fields in feed: params: 

            switch($key) {
                case 'content':
                    $result = self::processContent($feed, $params, $routePath, $key, $paramsData);
                    break;
                default:
                    // echopre("ERROR: Uknown field `$key` while rendering Feed");
                    // exit(-1);
            }
        }

        return $result;
    }

    static function prepareKeysFilter(dbQuery $q, array $keys) {
        foreach($keys as $keyname => $keyvalue) {
            if(is_array($keyvalue)) {
                // case where keys:
                //              params:
                //                  tag: [token1, token2]

                // echopre("[$index:$keyname] key equals an array");    //: " . print_r($keyvalue, 1));
                foreach($keyvalue as $keykeyname => $keykeyvalue) {
                    // echopre("[$index:$keyname:$keykeyname] key `$keykeyname`");  // => " . print_r($keykeyvalue, 1));

                    if(is_array($keykeyvalue)) {
                        // echopre("[$index:$keyname:$keykeyname] is JSON encoded: "); // . print_r($keykeyvalue, 1));

                        foreach($keykeyvalue as $key3value) {
                            // echopre("[$index:$keyname:$keykeyname] `$keyname:$keykeyname` can match `$key3value`");

                            $q = $q->whereJsonPathContains($keyname, $key3value, "'$.$keykeyname'");
                        }
                    } else {
                        // not yet tested(!)
                        echo("*** UNTESTED CODE ***");
                        $q = $q->whereLike($keyname, $keykeyvalue);  
                    }
                }
            } else {
                $q = $q->whereEq($keyname, $keyvalue);
            }
        }

        return $q;
    }

    static function processContent(mixed $feed, array $params, string $routePath, string $key, array $paramsData) {
        global $kernel;

        $result = [];

        foreach($paramsData as $index => $heading) {
            // echopre("[$index]: requesting content: type: {$heading['type']} => {$heading['name']}");

            if(!isset($heading['type'])) {
                echopre("ERROR: type is not set in content $index");
                exit(-1);
            }

            if(!isset($heading['name']) && !($heading['type'] === "cache")) {
                echopre("ERROR: name is not set in $index type {$heading['type']}");
                exit(-1);
            }

            switch($heading['type']) {
                case 'html':

                    $htmlText = contentPageClass::getContent($heading['name'] . '.html');
                    $result[] = $htmlText;
                    break;

                case 'feed':
                    $q = new dbQuery(dbConnection::getConnection(), $heading['name']);

                    if(isset($heading['keys'])) {
                        $q = self::prepareKeysFilter($q, $heading['keys']);
                    }

                    // now get feeds with query $q, filtering on language
                    // $arr = $q->whereEq('lang', $kernel->getCurrentLanguage())->cget();
                    $q= $q->whereEq('lang', $kernel->getCurrentLanguage());
                    $arr = $q->get();

                    // echopre("arr: " . print_r($arr, 1));
                    // echopre("SQL: " . $q->toSql());

                    foreach($arr as $el) {
                        // echopre("el: " . print_r($el, 1));
                        if(isset($heading['render'])) {
                            // echopre("[render] types: " . print_r(array_keys($heading['render']),1));
                            $contentText = null;
                            foreach($heading['render'] as $renderkey => $rendervalue) {
                                // $contentData = null;
                                // echopre("key: $renderkey => $rendervalue");
                                switch($renderkey) {
                                    case 'text':
                                        echopre("[$index]: rendering text: " . $heading['render']['text']);
                                        $contentText = Renderer::render('feeder-field-text.zetem', ['text' => $el[ $heading['render']['text'] ]]);
                                        // $contentText = $el[ $heading['render']['text'] ];
                                        break;
                                    case 'raw-text':
                                        echopre("[$index]: rendering text: " . $heading['render']['raw-text']);
                                        // $contentText = Renderer::render('feeder-field-text.zetem', ['text' => $el[ $heading['render']['text'] ]]);
                                        $contentText = $el[ $heading['render']['raw-text'] ];
                                        break;
                                    case 'feed':
                                        echopre("[$index]: rendering <b>feed</b>: " . $heading['render']['feed'] . " => " . $el['name']??"unknown element name");
                                        $htmlData = contentPageClass::getContent( $el[ $heading['render']['feed'] ] . '.html' );
                                        $contentText = Renderer::render('feeder-field-feed.zetem', ['html' => $htmlData ]);
                                        // $contentText = contentPageClass::getContent( $el[ $heading['render']['feed'] ] . '.html' );
                                        break;
                                    case 'template':
                                    case 'raw-template':
                                        // echopre("[$index]: rendering <b>template</b>: " . print_r($heading['render']['template'], 1) . " value: " . print_r($el, 1));


                                        // $tarr = [$routePath,, $params['name'], 
                                        // $heading['render']['template'],
                                            // $index, $heading['name']];
                                        // $txsugg = xgetTemplateSuggestions($tarr, 'feed');
                                        // echopre("Template suggestions: " . print_r($txsugg, 1));
                                        // $tsugg = [];
                                        $tsugg = Renderer::getTemplateSuggestionsShuffle([
                                                $routePath,
                                                $params['name'], 
                                                // $heading['render']['template'],
                                                $index, 
                                                $heading['name']
                                            ], 
                                            $heading['render'][ $renderkey ]
                                            // 'feed'
                                        );
                                        $tsugg = ['feeder'] + $tsugg;
                                        $tsugg = [$heading['render'][ $renderkey ]] + $tsugg;

                                        // echopre( "template suggestions: " . print_r( $tsugg, 1));

                                        $ttemplate = Renderer::getTemplate( $tsugg );
                                        // exit;
                                        // echopre('suggestion: ' . print_r($sugg, 1));
                                        // $template .= '.zetem';
                                        // echopre("selected template: $template");
                                        $tem_sugg = [$tsugg, $ttemplate];
                            
                                        $eldata = $el;
                                        $eldata['__class'] = [
                                            'route' => $routePath,
                                            'params' => $params/* ['name'] */,
                                            'table' => $heading['name'],
                                            'index' => $index,
                                        ];

                                        // echopre("printing template `$ttemplate` with array: " . print_r($eldata, 1));

                                        if($renderkey === 'raw-template') {
                                            $contentText = Renderer::renderRaw($ttemplate, $eldata, $tem_sugg);
                                        } else {
                                            $contentText = Renderer::render($ttemplate, $eldata, $tem_sugg);
                                        }

                                        // echopre("render templates: " . print_r($tem_sugg, 1));
                                        // echoprecode("Rendered text: " . print_r($contentText, 1));
                                        break;

                                    // case 'array':
                                        // echopre("[$index]: rendering <b>array</b>: " . $heading['render']['template']);
                                        // $contentData = [$renderkey => $el];
                                        // break;

                                    case 'cache':
                                        echopre("cache render value: " . print_r($rendervalue, 1));
                                        if(is_array($rendervalue)) {
                                            // echopre("render value is: " . print_r($rendervalue, 1), "p");
                                            if(!isset($rendervalue['key'])) {
                                                echopre("Please specify a `key` field for cache indexing ([$index]: $renderkey");
                                            }
                                            if(isset($rendervalue['index'])) {
                                                // if(!isset($cache_table[ $rendervalue['index'] ]))
                                                    // $cache_table[ $rendervalue['index'] ] = [];
                                                // echopre("el[ key:".$rendervalue['key']." ] => ".$el[$rendervalue['key']]." : rendervalue[ `index` ]" . ' => '. $rendervalue['index']);
                                                $cache_table[ $el[$rendervalue['index']] ][ $rendervalue['key'] ] = $contentText;
                                            } else {
                                                $cache_table[ $rendervalue['key'] ] = $contentText;   //$arr;
                                            }
                                            
                                        } else {
                                            if(!isset($cache_table[ $rendervalue ]))$cache_table[ $rendervalue ] = array();
                                            $cache_table[ $rendervalue ][] = $contentText;   //$arr;
                                            // $cache_table[] = [ $rendervalue => $contentText ];   //$arr;
                                        }
                                        // store text in cache array, key is the cache key

                                        // clear print text
                                        $contentText = null;
                                        break;

                                default:
                                    echopre("UNKNOWN rendering type $renderkey");
                                }

                                if(!isset($heading['render']['cache'])) {
                                    if(!empty($contentText)) {
                                        echopre("[$index]: adding to output stream: `$contentText`");
                                        $result[] = $contentText;
                                        // $contentText = '';
                                    }
                                }
                            }

                        }
                        // $result[] = $el[ $heading['render'] ];
                        if(!empty($contentText)) {
                            echopre("putting `$contentText` in results array");
                            $result[] = $contentText;
                        }

                    }
                    // $result[] = $arr;
                    // echopre("requested feeder data: " . print_r($arr, 1));

                    break;

                case 'cache':
                    // $result[] = 'cache hit';
                    // $result = [];
                    echopre("cache table: " . print_r($cache_table, 1),'pre', true);
                    $templ = $heading['render']['template']??null;

                    $cache_str = '';
                    $listStyle = $heading['list']??"";

                    switch($listStyle) {
                        case "unordered-list":
                            $cache_str .= "<ul>";
                            break;
                        case "html-table":
                            $cache_str .= "<table>";

                            // print table headers
                            $firstKey = array_key_first($cache_table);
                            $cache_str .= "<th>";
                            foreach($cache_table[ $firstKey ] as $tableKey => $tableValue) {
                                $cache_str .= "<td>$tableKey</td>";
                            }
                            $cache_str .= "</th>";
                            break;

                        default:
                    }

                    if($templ) {
                        foreach($cache_table as $entrykey => $entrydata) {
                            $args = ['info' => $entrydata];
                            echoprecode("rendering entry: " . print_r($args, 1));
                            $str = Renderer::render($templ.'.zetem', $args);


                            switch($listStyle) {
                                case "unordered-list":
                                    $cache_str .= "<li>$str</li>";
                                    break;
                                case "html-table":
                                    $cache_str .= "<tr>";
                                    foreach($entrydata as $entrydataKey => $entrydataValue) {
                                        $cache_str .= "<td>$entrydataValue</td>";
                                    }        
                                    $cache_str .= "</tr>";
                                    break;
                                default:
                                    $cache_str .= $str;
                            }
                            // echopre("rendering result: -->>" . print_r($str, 1). "<<---");
                        }
                    }

                    switch($listStyle) {
                        case "unordered-list":
                            $cache_str .= "</ul>";
                            break;
                        case "html-table":
                            $cache_str .= "</table>";
                            break;

                        default:
                    }


                    $result[] = $cache_str;
                    // $result[] = print_r($cache_table, 1);
                    // $result[] = $cache_table;

                    break;
            default:
                    echo ("ERROR: unknown content type: {$heading['type']}");
                    exit;
            }

        }

        $sugg = array();
        Renderer::getTemplateSuggestions(['type' => 'content', 'context' => $routePath, 'name' => $params['name']], function($args, &$suggestions) {
            if($args['type'] === 'content') {
                $suggestions[] = 'content';
                $suggestions[] = 'content-' . $args['context'];
                $suggestions[] = 'content--' . $args['name'];
                $suggestions[] = 'content-' . $args['context'] . '--' . $args['name'];
            }
        }, $sugg);

        $template = Renderer::getTemplate($sugg);
        // exit;
        echopre('suggestion: ' . print_r($sugg, 1));
        // $template .= '.zetem';
        echopre("feeder: selected template: $template");
        $template_sugg = [$sugg, $template];

        $template_args = [
            'content' => implode('<hr>', $result),
            // 'author' => $author,
            // 'date' => $date,
            // 'tags' => implode(' | ', $tags)
        ];
        // echopre("set $template and $template_args");
        if(Renderer::existsTemplate( $template )) {
            return Renderer::render($template, $template_args, $template_sugg??null);
        } else return error_404("Template `$template` was not found. Please call administrator!");
    
    }
}

