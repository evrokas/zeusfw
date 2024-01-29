<?php



require_once("../bootstrap.php");


$class = 'entityTest';

$e1 = new $class();

// $e1->dumpType();
// $e1->dumpType1();

$d = $e1->getAll();
foreach($d as $dat) {
    print_r( $dat);
}


// exit;
$r = $e1->getById( $argv[1]);
if($r)print_r( $r );
else echo ("No results\n");


$r->setemail( $argv[2] );
$r->update();


// $t = new entityTest();
// $t->loadFields(['username' => 'aggelos33', 'password'=>'test22', 'email'=>'rok.aggelos@gmail.com']);
// print_r( $t );
// $t->insert();

$r = $e1->getAll();
foreach($r as $rec) {
    print_r( $rec );
}

