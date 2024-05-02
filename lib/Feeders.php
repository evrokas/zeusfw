<?php

/*
 * Feeders php
 *
 */

class Feeder {
    private $feeder = null;
    private $hashes = array();
    function __construct($feedername) {
        $this->feeder = yaml_parse_file(__APPDIR__ . '/web' . $feedername);
        if(!isset($this->feeder['data']))
            $this->feeder['data'] = array();
        echopre(print_r( $this->feeder, 1 ));
        $ser = serialize($this->feeder['data']);

        $schema = yaml_parse_file(__APPDIR__ . '/web/classes/yaml/' . $this->feeder['schema']);
        echopre( print_r( $schema, 1));
        // $schemaclass = new $schema[0]['class']();
        // print_r( $schemaclass );

        if(isset($this->feeder['source'])) {
            foreach($this->feeder['source'] as $ysrc) {
                // echopre('source: ' . $ysrc);
                // echopre(__APPDIR__ .'/'. trim($this->feeder['content_dir'], ' /\\'). '/' . $ysrc . '/*.yml');
                $yfiles = glob(__APPDIR__ . '/' .trim($this->feeder['content_dir'], ' /\\'). '/' . $ysrc . '/*.yml');
                echopre('yfiles: ' . print_r($yfiles, 1));
                foreach($yfiles as $yf) {
                    $ydata = yaml_parse_file($yf);
                    // echopre(print_r($ydata, 1));
                    $this->feeder['data'] = array_merge($this->feeder['data'], $ydata['data']);
                }
            }
        }

        echopre(print_r($this->feeder['data'], 1));
        if(isset($this->feeder['order'])) {
            $this->feeder['data_ordered'] = array();
            foreach($this->feeder['order'] as $okey) {
                if(key_exists($okey, $this->feeder['data'])) {
                    $this->feeder['data_ordered'][] = $this->feeder['data'][$okey];
                    echopre(print_r($this->feeder['data'][$okey], 1));
                    $ser = serialize($this->feeder['data'][$okey]);
                    $hash = hash('sha256', $ser);
                    echopre('SHA256 data: ' . $hash);


                    $schemaclass = new $schema[0]['class']( $this->feeds['data'][$okey]);
                    print_r( $schemaclass );
            
                }
            }
        }
        echopre(print_r($this->feeder['data_ordered'], 1));
    }

    function hashdata(){}
}