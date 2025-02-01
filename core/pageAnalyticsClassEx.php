<?php

class pageAnalyticsClassEx extends pageAnalyticsClass {

    static function getPageByName($pagename) {
        $sql = "SELECT * FROM pageanalytics WHERE page=:pagename LIMIT 1";
        $st = dbConnection::getConnection()->prepare( $sql );

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
        $sql = "SELECT * FROM pageanalytics WHERE url=:url LIMIT 1";
        $st = dbConnection::getConnection()->prepare( $sql );

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
        $this->setcdate( getDBtime() );
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
                'rule' => '@',
                'total_count' => 0,
                'week_count' => 0,
                'last_week_count' => 0,

                'month_count' => 0,
                'last_month_count' => 0,
            ]);
            $stat->insert();

        } else {
            // $stat->setcdate(getDBtime());
            // $stat->update();
        }
    }

    static function getStatisticsRecord() {
        $stat = pageAnalyticsClassEx::getPageByName('__system_statistics');
        if(!$stat) {
            self::initializePageAnalyticsRecord();
            $stat = pageAnalyticsClassEx::getPageByName('__system_statistics');
        }

        return $stat;
    }

    static function newWeek() {
        // update last_week with current week, in one sql command
        $sql = "UPDATE pageanalytics SET last_week_count = week_count, week_count = 0, cdate = :cdate";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":cdate", getDBtime(), PDO::PARAM_STR);

        $st->execute();

                // this is very inefficient(!)
        // $pages = pageAnalyticsClassEx::sgetAll();
        // foreach($pages as $pag) {
        //     $pag->setlast_week_count( $pag->getweek_count() );
        //     $pag->setweek_count( 0 );
        //     // $pag->setcdate( getDBtime() );
        //     $pag->update();
        // }
    }

    static function newMonth() {
        // update last_week with current week, in one sql command
        $sql = "UPDATE pageanalytics SET last_month_count = month_count, month_count = 0, cdate = :cdate";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":cdate", getDBtime(), PDO::PARAM_STR);

        $st->execute();
        
        // this is very inefficient(!)
        // $pages = pageAnalyticsClassEx::sgetAll();
        // foreach($pages as $pag) {
        //     $pag->setlast_month_count( $pag->getmonth_count() );
        //     $pag->setmonth_count( 0 );
        //     // $pag->setcdate( getDBtime() );
        //     $pag->update();
        // }
    }

    static function updateStatisticsPage($weekno, $monthno = null, $stat = null) {
        // if both are null, do nothing
        if(!$weekno && !$monthno)return;

        if(!$stat) {
            $stat = self::getStatisticsRecord();
        }

        $sql = "UPDATE pageanalytics SET ";
        $values = [];
        $assign = [];

        $assign['cdate'] = getDBtime();

        if($weekno) {
            // update week number
            $assign['last_week_count'] = $weekno;
        }

        if($monthno) {
            // update month number
            $assign['last_month_count'] = $monthno;
        }

        $finalassigns = [];
        foreach($assign as $key => $val) {
            $finalassigns[] = "$key = :$key";
            $values[$key] = $val;
        }

        $sql .= implode(',', $finalassigns);
        
        $sql .= " WHERE page = :pagename";

        echopre(print_r($sql, 1));

        $st = dbConnection::getConnection()->prepare($sql);
        foreach($values as $key => $val) {
            $st->bindValue(":$key", $val);
        }

        // if($weekno)
        //     $st->bindValue(':weekno', $weekno, PDO::PARAM_INT);
        // if($monthno)
        //     $st->bindValue(':monthno', $monthno, PDO::PARAM_INT);

        $st->bindValue(':pagename', $stat->getpage(), PDO::PARAM_STR);
        $st->execute();
    }
}
