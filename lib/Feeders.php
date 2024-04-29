<?php

/*
 * Feeders php
 *
 */

class Feeder {
    function __construct($feedername) {
        $yaml = yaml_parse_file(__APPDIR__ . '/web' . $feedername);
        if(!isset($yaml['data']))
            $yaml['data'] = array();
        echopre(print_r( $yaml, 1 ));
        $ser = serialize($yaml['data']);

        $schema = yaml_parse_file(__APPDIR__ . '/web/classes/yaml/' . $yaml['schema']);
        // echopre( print_r( $schema, 1));

        if(isset($yaml['source'])) {
            foreach($yaml['source'] as $ysrc) {
                // echopre('source: ' . $ysrc);
                $yfiles = glob(__APPDIR__ . '/web/' . $ysrc . '/*.yml');
                echopre('yfiles: ' . print_r($yfiles, 1));
                foreach($yfiles as $yf) {
                    $ydata = yaml_parse_file($yf);
                    // echopre(print_r($ydata, 1));
                    $yaml['data'] = array_merge($yaml['data'], $ydata['data']);
                }
            }
        }
        echopre(print_r($yaml['data'], 1));
        if(isset($yaml['order'])) {
            $yaml['data_ordered'] = array();
            foreach($yaml['order'] as $okey) {
                if(key_exists($okey, $yaml['data'])) {
                    $yaml['data_ordered'][] = $yaml['data'][$okey];
                    echopre(print_r($yaml['data'][$okey], 1));
                    $ser = serialize($yaml['data'][$okey]);
                    $hash = hash('sha256', $ser);
                    echopre('SHA256 data: ' . $hash);
                }
            }
        }
        echopre(print_r($yaml['data_ordered'], 1));
    }
}