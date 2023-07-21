<?php

ob_start('ob_gzhandler');
// error_reporting(0);
define("APP_ROOT", dirname(dirname(__FILE__)));
define("CORE_PATH", APP_ROOT . "/core");
define("PRIVATE_PATH", APP_ROOT . "/API");


require_once(CORE_PATH . "/Headers.php");

