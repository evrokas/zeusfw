<?php

require_once("../bootstrap.php");
require_once("../db/dbal.php");
require_once("./info.php");


$tst = new info();
print_r( $tst );

$r = $tst->getAll();

print_r( $r );


$r = $tst->getById(1);
echo "id=1 change email\n";
print_r( $r );
$r->setemail("test@email.com");
$r->update();

$rr = $tst->getById(1);
print_r( $rr );

if(0) {
    echo "delete id=2\n";
    $r = $tst->getById(2);
    $r->delete();
    
    $rr = $tst->getAll();
    print_r( $rr );
}

$r = new info(['name' => 'rokas', 'age' => '32', 'email' => 'myownemail@email.com']);
$r->insert();

$rr = $tst->getAll();
print_r( $rr );
