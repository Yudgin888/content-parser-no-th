<?php
include_once "db_conf.php";

function saveAll($urls, $overwrite = true)
{
    global $host, $login, $password, $database;
    $link = mysqli_connect($host, $login, $password, $database);
    if (!$link || count($urls) == 0) {
        return false;
    } else {
        createTable($overwrite);
        foreach ($urls as $url) {
            $query = "INSERT INTO `urls` (`url`, `depth`, `status`)
              VALUES ('" . $url['url'] . "', " . $url['depth'] . ", '" . $url['status'] . "');";
            mysqli_query($link, $query);
        }
        mysqli_close($link);
        return true;
    }
}

function createTable($overwrite)
{
    global $host, $login, $password, $database;
    $link = mysqli_connect($host, $login, $password, $database);
    if (!$link) {
        return false;
    } else {
        if ($overwrite) {
            $query = "DROP TABLE IF EXISTS `urls`;";
            mysqli_query($link, $query);
        }
        $query = "CREATE TABLE `urls` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `url` VARCHAR(255) NOT NULL,
              `depth` INT NULL,
              `status` VARCHAR(45) NULL,
              PRIMARY KEY (`id`));";

        mysqli_query($link, $query);
        mysqli_close($link);
        return true;
    }
}