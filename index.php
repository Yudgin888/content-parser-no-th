<?php
require_once "Urlparser.php";
ini_set("display_errors",1);
error_reporting(E_ALL);
$start = microtime(true);

$url = "http://simonenko.su";
//$url = "https://wiki.pwodev.com";

$parser = new Urlparser();
$parser->parse($url, true, ALL, 0, true);

echo '<br>Done!<br>';
echo 'Всего: ' . count($parser->links) . '<br>';
echo 'Уникальных: ' . ($parser->internal_counter + $parser->external_counter) . '<br>';
echo 'Всего внутренних: ' . $parser->internal_counter . '<br>';
echo 'Всего внешних: ' . $parser->external_counter . '<br>';
$time = microtime(true) - $start;
echo 'Time: ' . $time . '<br>';
die;