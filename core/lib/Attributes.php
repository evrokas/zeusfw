<?php

// attributes
/*
 * Attributes:
 *  class
 *  role
 *  data
 *  etc...
 */

class Attributes {
    private $attr;

    function __construct(array $attrs = []) {
        $this->attr = array();

        if(is_array($attrs) && !empty($attrs))
            $this->addAttribute( $attrs );
    }

    function existAttribute($attrclass, $attrname) {
        if(array_key_exists($attrclass, array_keys($this->attr))) {
            if(!in_array($attrname, $this->attr[$attrclass])) {
                return true;
            } else return false;
        } else return false;
    }

    // deprecated
    function addAttributeS(string $attrclass, string $attrname) {
        if(!isset($this->attr[ $attrclass ]))
            $this->attr[ $attrclass ] = array();
    
        if(!$this->existAttribute($attrclass, $attrname)) {
            // $attrname does not exist, so add it
            $this->attr[ $attrclass ][] = $attrname;
        }

        // print_r( $this->attr );
    }

    function addAttribute(array $attrs) {
        if(!is_array($attrs))return;

        foreach($attrs as $attrclass => $attrvalue) {
            if(!isset($this->attr[ $attrclass ]))
                $this->attr[ $attrclass ] = [];

            // do not add duplicate attribute values
            if(!$this->existAttribute($attrclass, $attrvalue)) {
                $this->attr[ $attrclass ][] = $attrvalue;
            }
        }

    }

    function removeAttribute($attrclass, $attrname) {
        if(!isset($this->attr[ $attrclass ]))return;
        if(isset($this->attr[ $attrclass ][ $attrname ]))
            unset($this->attr[ $attrclass ][ $attrname ]);
    }

    // shorthands for class="class"
    function addClass($attrname) {
        $this->addAttribute(['class' => $attrname ]);
    }

    function removeClass($attrname) {
        $this->removeAttribute('class', $attrname);
    }

    function getAttributes() {
        $eqls = [];
        foreach($this->attr as $attrclass => $attrdata) {
            // echo "atribute data: " . $attrdata;
            if(count($attrdata))
                $eqls[] = $attrclass . "=\"" . implode(' ', $attrdata) . "\"";
            else $eqls[] = $attrclass;
        }

        $str = implode(' ', $eqls);
    
      return ($str);
    }
}
