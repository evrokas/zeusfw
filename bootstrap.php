<?php

include_once(__DIR__ . "/../config/db.php");       // load database parameters

include_once(__DIR__ . "/kernel/Kernel.php");       // include Kernel class
require_once(__DIR__ . "/db/dbal.php");            // load database classes


// include user classes only if it exists
if(file_exists('../src/user_classes.php'))
    include_once(__DIR__ . "/../src/user_classes.php");


require_once(__DIR__ . "/security/Permissions.php");    // security permissions classes

require_once(__DIR__ . "/router/Router.php");      // load router classes
require_once(__DIR__ . "/router/Request.php");      // load router classes
require_once(__DIR__ . "/router/ErrorHandlers.php");      // load router classes

require_once(__DIR__ . "/templates/ZETEMTemplate.php");


require_once(__DIR__ . "/lib/Attributes.php");
require_once(__DIR__ . "/lib/Modules.php");
require_once(__DIR__ . "/lib/Content.php");

require_once(__DIR__ . "/lib/Menutrail.php");
require_once(__DIR__ . "/lib/Routetrail.php");
require_once(__DIR__ . "/lib/Security.php");

// moved to ../src/
// if(file_exists('../src/ClassesEx.php'))
// require_once(__DIR__ . "/lib/ClassesEx.php");