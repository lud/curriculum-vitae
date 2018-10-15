<?php

if (PHP_SAPI !== 'cli-server') {
    exit('router.php is for devserver.');
}

if (is_file($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_NAME'])) {
    return false;
}

require 'index.php';
