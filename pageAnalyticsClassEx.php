<?php

class pageAnalyticsClassEx extends pageAnalyticsClass {

    static function getPageByName($pagename) {
        // get hash 
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return null;
            }
        }

        $sql = "SELECT * FROM pageanalytics WHERE page=:pagename LIMIT 1";
        $st = $AppDBConnection->getConnection()->prepare( $sql );

        $st->bindValue(":pagename", $pagename, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            // prelog(print_r($row, 1));
            $rclass = new pageAnalyticsClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }

    static function getPageByURL($url) {
        // get hash 
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return null;
            }
        }

        $sql = "SELECT * FROM pageanalytics WHERE url=:url LIMIT 1";
        $st = $AppDBConnection->getConnection()->prepare( $sql );

        $st->bindValue(":url", $url, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            // prelog(print_r($row, 1));
            $rclass = new pageAnalyticsClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }

    function increase() {
        $this->settotal_count( $this->gettotal_count()+1);
        $this->setweek_count($this->getweek_count()+1);
        $this->setmonth_count($this->getmonth_count()+1);
    }

    function reset_total_count() { $this->settotal_count( 0 ); }

    function reset_week_count() { $this->setweek_count( 0 ); }

    function reset_month_count() { $this->setmonth_count( 0 ); }

    function reset_all() {
        $this->reset_total_count();
        $this->reset_week_count();
        $this->reset_month_count();
    }


    static function initializePageAnalyticsRecord() {
        $stat = pageAnalyticsClassEx::getPageByName('__system_statistics');
        if(!$stat) {
            $stat = new pageAnalyticsClass([
                'cdate' => getDBtime(),
                'page' => '__system_statistics',
                'url' => '__system_statistics',
                'total_count' => 0,
                'week_count' => 0,
                'last_week_count' => 0,

                'month_count' => 0,
                'last_month_count' => 0,
            ]);
            $stat->insert();

        } else {
            $stat->setcdate(getDBtime());
            $stat->update();
        }
    }

}