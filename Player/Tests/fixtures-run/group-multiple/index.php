<?php

$counter = (int) ($_COOKIE['counter'] ?? 0);
++$counter;
header('Set-Cookie: counter='.$counter);

echo sprintf(
    '%s %d',
    $_GET['q'] ?? '-',
    $counter
);
