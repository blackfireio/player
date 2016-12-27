<?php

$i = isset($_GET['i']) ? $_GET['i'] : 0;

if ($i >= 4) {
    echo 'OK';

    return;
}

header('HTTP/1.0 302 Found');
header(sprintf('Location: %s?i=%d', $_SERVER['SCRIPT_NAME'], ++$i));

echo 'Redirect';
