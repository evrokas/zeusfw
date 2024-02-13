<?php

include_once(__DIR__ . "/../config/db.php");       // load database parameters

include_once(__DIR__ . "/kernel/Kernel.php");       // include Kernel class

include_once(__DIR__ . "/classes/bootstrap_classes.php");

require_once(__DIR__ . "/db/dbal.php");            // load database classes

require_once(__DIR__ . "/security/Permissions.php");    // security permissions classes

require_once(__DIR__ . "/router/Router.php");      // load router classes
require_once(__DIR__ . "/router/Request.php");      // load router classes
require_once(__DIR__ . "/router/ErrorHandlers.php");      // load router classes

require_once(__DIR__ . "/templates/ZETEMTemplate.php");
