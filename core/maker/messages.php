<?php

function message_new($user, $title, $message) {
    echo("Sending user $user the message title `$title` and body `$message`\n");

    if(str_starts_with($title, '@')) {
        // read title from file
        $title = file_get_contents(explode('@', $title)[1]);
    }

    if(str_starts_with($message, '@')) {
        // read message from file
        $message = file_get_contents(explode('@', $message)[1]);
    }

    DIR::$fw = DIR::$app . '/web/core';
    
    // echo("DIR::app: " . DIR::$app . "\n");
    // echo("DIR::fw: " . DIR::$fw . "\n");
    include_once(DIR::$app . '/config/db.php');
    require(DIR::$fw . "/bootstrap.php");
    
    
    dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    dbConnection::Connect();

    $pdo = dbConnection::getConnection();

    $msg = new messageClass();
    $msg->setcuser('admin');
    $msg->setcdate(getDBtime());
    $msg->setmdate(getDBtime());
    $msg->setrecepient( $user );
    $msg->settitle( $title );
    $msg->setmessage( $message );
    $msg->setlanguage('en');

    $msg->insert();

    echo("Message send succesfully.\n");
}