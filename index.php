<?php
require_once "Urlparser.php";
ini_set("display_errors",1);
error_reporting(E_ALL);
$start = microtime(true);

$url = "http://zanachka.isumkaby.vh94.hosterby.com/catalog/category/chemodany_na_2_kolesakh";
$parser = new Urlparser();
$parser->parse($url, true, true, false);


echo '<br>Done!<br>';
echo 'Total url: ' . count($parser->links) . '<br>';
echo 'Time: ' . (microtime(true) - $start) . '<br>';
die;