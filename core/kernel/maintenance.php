<?php
/**
 * maintenance.php
 * 
 * perform periodic tasks on webpage or framework
 * 
 *  - update pageanalytics
 *  - clear cache
 *  - clear cached files
 */

class Maintenance {
    static private $last_call = null;      // last time class was called
    static private $has_analytics = false;
    static private $has_pageanalytics = false;

    static function init() {
        self::update_time();

        self::$has_analytics = class_exists("analyticsClass");
        self::$has_pageanalytics = class_exists("pageAnalyticsClass");
    }

    static function update_time() {
        self::$last_call = time();
    }

    static function time_elapsed() {
        return (time() - self::$last_call);
    }

    static function update_analytics_counter() {
        // global $kernel;

        if(self::$has_pageanalytics) {
            $pages = pageAnalyticsClass::sgetAllFilter('pageanalytics', [], ["cdate" => "DESC"]);
            // $pages[0] is system statistics record
            // $tim = strtotime($pages[1]->getcdate());
            $tim = time();

            $currentDate = new DateTime();
            $currentWeek = $currentDate->format('W');
            $currentMonth = $currentDate->format('n');

            $stats = pageAnalyticsClassEx::getStatisticsRecord();
            $lastWeek = $stats->getlast_week_count();
            $lastMonth = $stats->getlast_month_count();

            $resetWeek = $currentWeek != $lastWeek;
            $resetMonth = $currentMonth != $lastMonth;

            // echopre("Current week $currentWeek month $currentMonth last update week $lastWeek month: $lastMonth");
            // echopre("Update week: " . ($resetWeek?"true":"false") . ", update month: " . ($resetMonth?"true":"false") );

            if(!$resetWeek) {
                $currentWeek = null;
            } else {
                pageAnalyticsClassEx::newWeek();
            }
            
            if(!$resetMonth) {
                $currentMonth = null;
            } else {
                pageAnalyticsClassEx::newMonth();
            }


            pageAnalyticsClassEx::updateStatisticsPage($currentWeek, $currentMonth, $stats);
        }

    }


    static function remove_expired_user_tokens() {
        userTokensClassEx::remove_expired_user_tokens();
    }


    static function maintenance() {
        // update page analytics statistics, and 
        // perform rotation if new week, or new month
        self::update_analytics_counter();


        // remove expired user tokens
        self::remove_expired_user_tokens();
    }
}
