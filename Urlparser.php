<?php
require_once "simplehtmldom/simple_html_dom.php";
set_time_limit(0);
define("INTERNAL", 1); // только внутренние
define("EXTERNAL", 2); // только внешние
define("ALL", 3); // внутренние и внешние (переход на внешние не делается)

class Urlparser
{
    public $max_depth; // глубина прохода по ссылкам
    public $checkstatus; // проверка http статуса
    public $transition; // стратегия отсеивания ссылок (INTERNAL / EXTERNAL / ALL)

    private $save_to_db;
    private $starturl;
    private $root; // корневой адрес сайта

    public $links; // обработанные ссылки
    // структура
    // 'url' => $url
    // 'status' => http статус
    // 'depth' => глубина ссылки от начальной
    // 'parrent' => родительская ссылка
    // 'type' => тип ссылки

    private $unique_links; // массив уникальных ссылок

    public $internal_counter;
    public $external_counter;

    public function __construct()
    {
        $this->unique_links = [];
        $this->links = [];
        $this->internal_counter = 0;
        $this->external_counter = 0;
    }

    public function parse($url, $checkstatus = false, $transition = INTERNAL, $max_depth = 0, $save_to_db = false)
    {
        $this->max_depth = $max_depth;
        $this->save_to_db = $save_to_db;
        $this->checkstatus = $checkstatus;
        $this->transition = $transition;
        $this->starturl = $url;
        $parts = parse_url($url);
        $this->root = $parts['scheme'] . '://' . $parts['host'];
        $this->unique_links[$url] = 1;
        $this->worker($url);

        if ($save_to_db) {
            $this->saveToDB(true);
        }
    }

    private function worker($url, $depth = 0, $parrent_url = null)
    {
        if ($this->max_depth != 0 && $depth > $this->max_depth) {
            return;
        }
        $typeUrl = $this->checkTypeUrl($url);
        if ($typeUrl === $this->transition || $this->transition === ALL) {
            $arr = [
                'url' => $url,
                'status' => $this->checkstatus ? $this->getStatus($url) : null,
                'depth' => $depth,
                'parrent' => $parrent_url,
                'type' => $typeUrl,
            ];
            $this->links[] = $arr;
            if ($typeUrl === INTERNAL) {
                $this->internal_counter++;
            } else {
                $this->external_counter++;
            }
            echo $arr['url'] . ' - status: ' . $arr['status'] . ' - depth: ' . $arr['depth'] . '<br>';
            flush();
        }
        if ($typeUrl === INTERNAL) {
            $this->page_parser($url, $depth + 1);
        }
    }

    private function page_parser($url, $depth)
    {
        $html = new simple_html_dom();
        try {
            $html->load_file($url);
            if ($html !== null && is_object($html) && isset($html->nodes) && count($html->nodes) > 0) {
                $alllinks = $html->find('a[href]');
                foreach ($alllinks as $link) {
                    $href = $link->attr['href'];
                    if ($href != null) {
                        if (preg_match('/\.(png|jpeg|gif|jpg|js|css|xml|pdf)/', $href)) {
                            continue;
                        }
                        $href = $this->prepareUrl($href);
                        if (!isset($this->unique_links[$href])) {
                            $this->unique_links[$href] = 1;
                            $this->worker($href, $depth, $url);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            echo 'Error: ' . $url . PHP_EOL;
        }
        $html->clear();
    }

    public function getStatus($url)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_exec($handle);
        return curl_getinfo($handle, CURLINFO_HTTP_CODE);
    }

    public function saveToDB($overwrite)
    {
        if (file_exists("mysql_wrapper.php")) {
            include_once "mysql_wrapper.php";
            saveAll($this->links, $overwrite);
            return true;
        } else {
            return false;
        }
    }

    public function checkTypeUrl($url)
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme']) && !isset($parts['host'])) {
            return INTERNAL;
        } else {
            if (strcmp($this->root, $parts['scheme'] . '://' . $parts['host']) === 0) {
                return INTERNAL;
            } else return EXTERNAL;
        }
    }

    private function prepareUrl($url)
    {
        if ($url === '/') {
            $url = $this->root;
        }
        $parts = parse_url($url);
        if (!isset($parts['scheme']) && !isset($parts['host'])) {
            $url = $this->root . $url;
        } elseif (!isset($parts['scheme'])) {
            $url = 'http:' . $url;
        }
        if(isset($parts['fragment'])){
            $url = substr($url, 0, strpos($url, '#'));
        }
        return $url;
    }
}