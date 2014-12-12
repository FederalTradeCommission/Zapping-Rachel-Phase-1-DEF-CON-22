<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

set_include_path( realpath(dirname(__FILE__) . '/../application') . PATH_SEPARATOR . get_include_path());
require("Bootstrap.php");
$app = new Bootstrap("Call","incoming");