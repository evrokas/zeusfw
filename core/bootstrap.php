<?php

if(!defined('__BOOTSTRAP_DIR__'))
    define("__BOOTSTRAP_DIR__", __DIR__);

// Must come first: Kernel's constructor calls yaml_parse_file() immediately, so
// the fallback has to be defined before anything can reach it. No-op when the
// PHP yaml extension is loaded - see the header of yaml_compat.php.
require_once(__DIR__ . "/lib/yaml_compat.php");

include_once(__DIR__ . "/kernel/Kernel.php");       // include Kernel class
require_once(__DIR__ . "/kernel/utils.php");
require_once(__DIR__ . "/db/dbal.php");            // load database abstract classes


// include framework classes
if(file_exists(__DIR__ . "/classes/bootstrap_classes.php"))
    require_once(__DIR__ . "/classes/bootstrap_classes.php");	// load framework classes

if(file_exists(__DIR__ . "/ClassExFW.php")) {
    include_once(__DIR__ . "/ClassExFW.php");        // framework class extentions
}

// include user classes only if they exist
if(file_exists(__APPDIR__ . '/web/user_classes.php')) {
    include_once(__APPDIR__ . "/web/user_classes.php");
} else
error_log(__APPDIR__ . "/web/user_classes.php was not found\n");


//require_once(__DIR__ . "/security/Permissions.php");    // security permissions classes

require_once(__DIR__ . "/router/Router.php");      // load router classes
require_once(__DIR__ . "/router/Request.php");      // load router classes
require_once(__DIR__ . "/router/ErrorHandlers.php");      // load router classes

require_once(__DIR__ . "/templates/ZETEMTemplate.php");


require_once(__DIR__ . "/lib/Log.php");

require_once(__DIR__ . "/lib/Attributes.php");
require_once(__DIR__ . "/lib/Modules.php");
require_once(__DIR__ . "/lib/ContentPage.php");
require_once(__DIR__ . "/lib/Recaptcha.php");

require_once(__DIR__ . "/lib/Menutrail.php");
require_once(__DIR__ . "/lib/Routetrail.php");
require_once(__DIR__ . "/lib/Security.php");
require_once(__DIR__ . "/lib/Rbac.php");
require_once(__DIR__ . "/lib/Packages.php");
// Route handlers for the admin_user_crud package (routes registered in
// core/config/zeusfw.info.yaml, unconditionally for every app -- see that
// file's own comments). Required unconditionally here too, so the handler
// functions always exist to dispatch to; packagesClass::isEnabled() inside
// each one is what actually makes the package effectively unavailable when
// disabled, not whether this file was required.
require_once(__DIR__ . "/modules/admin/admin_crud.php");
require_once(__DIR__ . "/lib/Csrf.php");
require_once(__DIR__ . "/lib/CookieSecurity.php");
require_once(__DIR__ . "/lib/UserLogin.php");
// require_once(__DIR__ . "/lib/Feeders.php");
require_once(__DIR__ . "/lib/Feeder.php");

require_once(__DIR__ . "/lib/FormElement.php");

require_once(__DIR__ . "/lib/WebForms.php");


// moved to ../src/
// if(file_exists('../src/ClassesEx.php'))
// require_once(__DIR__ . "/lib/ClassesEx.php");


require_once('kernel/maintenance.php');