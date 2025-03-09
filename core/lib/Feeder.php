<?php

/*
 * Feeders php
 *
 */

class Feeder {

    static function renderFeed(array $params) {
        foreach($params as $key => $data) {
            // transverse all fields in $params
            switch($p) {
                case 'content':
                    $result = self::processContent($data);
                    break;
                default:
                    echopre("ERROR: Uknown field while rendering Feed");
                    exit(-1);
            }
        }

        return $result;
    }

    static function prepareKeysFilter(dbQuery $q, array $keys) {
        if(is_array($keyvalue)) {
            // case where keys:
            //              params:
            //                  tag: [token1, token2]
        
            echopre("key equals to array: " . print_r($keyvalue, 1));
            foreach($keyvalue as $keykeyname => $keykeyvalue) {
                echopre("\tkey `$keykeyname` => " . print_r($keykeyvalue, 1));
                if(is_array($keykeyvalue)) {
                    echopre("key-key-value is also an array: " . print_r($keykeyvalue, 1));
                    foreach($keykeyvalue as $key3value) {
                        echopre("$keyname like $key3value");
                        $q = $q->whereJsonPathContains($keyname, $key3value, "'$.$keykeyname'");
                    }
                }
                // $q = $q->whereLike($keyname, $keykeyvalue);  
            }
        } else {
            $q = $q->whereEq($keyname, $keyvalue);
        }

        return $q;
    }

    static function processContent(array $data) {
    global $kernel;

        $result = [];

        foreach($data as $index => $heading) {
            echopre("[$index]: requesting content: type: {$heading['type']} => {$heading['name']}");

            if(!isset($heading['type'])) {
                echopre("ERROR: type is not set in content $index");
                exit(-1);
            }

            if(!isset($heading['name'])) {
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
                    $arr = $q->whereEq('lang', $kernel->getCurrentLanguage())->get();
                    // echopre("arr: " . print_r($arr, 1));
                    // $arr = contentBaseClass::squery()->whereEq('contenttype', $feederopt)->whereEq($feederkey, $feederkeyvalue)->whereEq('lang', $kernel->getCurrentLanguage())->cget();
                    echopre("SQL: " . $q->toSql());

                    foreach($arr as $el) {
                        echopre("el: " . print_r($el, 1));
                    
                        if(isset($rcont['render'])) {
                            if(isset($rcont['render']['text'])) {
                                echopre("rendering text: " . $rcont['render']['text']);
                                $contentText = $el[ $rcont['render']['text'] ];
                            } else
                            if(isset($rcont['render']['feed'])) {
                                echopre("rendering <b>feeder</b>: " . $rcont['render']['feeder']);
                                $contentText = contentPageClass::getContent( $el[ $rcont['render']['feeder'] ] . '.html' );
                            } else
                            if(isset($rcont['render']['template'])) {
                                echopre("rendering <b>template</b>: " . $rcont['render']['template']);

                                $contentText = Renderer::render($rcont['render']['template'] .'.zetem', $el);
                            }
                        }
                        // $content_list[] = $el[ $rcont['render'] ];
                        $content_list[] = $contentText;
                    }
                    // $content_list[] = $arr;
                    // echopre("requested feeder data: " . print_r($arr, 1));

                    break;
            default:
                    

            }
        }
    }
        foreach($vparams['content'] as $index => $rcont) {
            // echopre("rcont: " . print_r($rcont, 1));
            switch($rcont['type']) {
                case "html":
                    $cntname = $rcont['name'];
                    // echopre("requesting contens of file $cntname,html");
                    $contentText = contentPageClass::getContent( $cntname . '.html' );
                    // echopre("contents of requested file: " . print_r($contentText, 1));
                    $content_list[] = $contentText;
                    break;

                case "feed":
                    // $q = htmlcontentClass::squery(); // initiate a query;
                    $q = new dbQuery(dbConnection::getConnection(), $rcont['name']);    //, "contentBaseClass");
                    // $q = $q->table('content');
                    
                    if(isset($rcont['keys'])) {
                        foreach($rcont['keys'] as $keyname => $keyvalue) {
                            echopre("key: $keyname => " . print_r($keyvalue, 1));

                            if(is_array($keyvalue)) {
                                // case where keys:
                                //              params:
                                //                  tag: [token1, token2]
                            
                                echopre("key equals to array: " . print_r($keyvalue, 1));
                                foreach($keyvalue as $keykeyname => $keykeyvalue) {
                                    echopre("\tkey `$keykeyname` => " . print_r($keykeyvalue, 1));
                                    if(is_array($keykeyvalue)) {
                                        echopre("key-key-value is also an array: " . print_r($keykeyvalue, 1));
                                        foreach($keykeyvalue as $key3value) {
                                            echopre("$keyname like $key3value");
                                            $q = $q->whereJsonPathContains($keyname, $key3value, "'$.$keykeyname'");
                                        }
                                    }
                                    // $q = $q->whereLike($keyname, $keykeyvalue);  
                                }
                            } else {
                                $q = $q->whereEq($keyname, $keyvalue);
                            }
                        }
                    }

                    // $arr = $q->whereEq('lang', $kernel->getCurrentLanguage())->cget();
                    $arr = $q->whereEq('lang', $kernel->getCurrentLanguage())->get();
                    // echopre("arr: " . print_r($arr, 1));
                    // $arr = contentBaseClass::squery()->whereEq('contenttype', $feederopt)->whereEq($feederkey, $feederkeyvalue)->whereEq('lang', $kernel->getCurrentLanguage())->cget();
                    echopre("SQL: " . $q->toSql());
                    foreach($arr as $el) {
                        echopre("el: " . print_r($el, 1));
                        // echopre("Text: " . $el->getcontent());
                        // $content_list[] = $el->getFields()[ $rcont['render'] ];
                        if(isset($rcont['render'])) {
                            if(isset($rcont['render']['text'])) {
                                echopre("rendering text: " . $rcont['render']['text']);
                                $contentText = $el[ $rcont['render']['text'] ];
                            } else
                            if(isset($rcont['render']['feed'])) {
                                echopre("rendering <b>feeder</b>: " . $rcont['render']['feeder']);
                                $contentText = contentPageClass::getContent( $el[ $rcont['render']['feeder'] ] . '.html' );
                            } else
                            if(isset($rcont['render']['template'])) {
                                echopre("rendering <b>template</b>: " . $rcont['render']['template']);

                                $contentText = Renderer::render($rcont['render']['template'] .'.zetem', $el);
                            }
                        }
                        // $content_list[] = $el[ $rcont['render'] ];
                        $content_list[] = $contentText;
                    }
                    // $content_list[] = $arr;
                    // echopre("requested feeder data: " . print_r($arr, 1));

                    break;
            default:
                    return ("ERROR: unknown content type: {$rcont['type']}");
                    exit;
            }

        }

    }

}