<?php

$location =  __DIR__;
$sql = file_get_contents('storage.sql');

chdir('../../../');
$root = realpath('.');

require_once 'config.inc.php';
require_once 'include/database/PearDatabase.php';

$seeding = $adb->query($sql);

echo 'Done. Errors: ' . var_export($adb->database->ErrorMsg(), 1);
