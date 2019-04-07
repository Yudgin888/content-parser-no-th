<?php
require_once "simplehtmldom/simple_html_dom.php";
require_once "mysql_wrapper.php";
set_time_limit(0);
define("INTERNAL", 1);
define("EXTERNAL", 2);

class Urlparser
{
    private $save_to_db;
    private $root; // корневой адрес сайта
    private $start_url;
    private $all_directions; // проход по ссылкам (false - не ниже стартовой)
    private $del_get;

    public $links; // обработанные ссылки
    //      структура
    // 'url' => $url
    // 'status' => http статус

    public $unique_links; // массив уникальных ссылок

    public $products;
    // 'name' - название
    // 'url' - страница товара
    // 'article' - артикул
    // 'price' - цена
    // 'description' - описание
    // 'images' - json
    // 'dir_img' - папка с изобр

    public function __construct()
    {
        $this->unique_links = [];
        $this->links = [];
        $this->products = [];
    }

    public function parse($url, $all_directions = false, $del_get = true, $save_to_db = false)
    {
        $this->del_get = $del_get;
        $this->save_to_db = $save_to_db;
        $this->all_directions = $all_directions;
        $this->start_url = $url;
        $parts = parse_url($url);
        $this->root = $parts['scheme'] . '://' . $parts['host'];
        $this->unique_links[$url] = 1;
        $this->worker($url);

        if ($save_to_db) {
            $this->saveToDB(true);
        }
    }

    private function worker($url)
    {
        $typeUrl = $this->checkTypeUrl($url);
        if ($typeUrl === INTERNAL) {
            $arr = [
                'url' => $url,
                'status' => $this->getStatus($url),
            ];
            $this->links[] = $arr;
            echo $arr['url'] . ' - status: ' . $arr['status'] . '<br>';
            flush();
            $this->page_parser($url);
        }
    }

    private function page_parser($url)
    {
        $html = new simple_html_dom();
        try {
            $html->load_file($url);
            if ($html !== null && is_object($html) && isset($html->nodes) && count($html->nodes) > 0) {
                $product_card = $html->find('.catalog_detail .item_main_info');
                foreach ($product_card as $item) {
                    $name = $html->find('#pagetitle')[0]->innertext;
                    $article = $item->find('.article .value')[0]->innertext;
                    $price = $item->find('.prices_block .price_value')[0]->innertext . $item->find('.prices_block .price_currency')[0]->innertext;
                    $description = $item->find('.preview_text')[0]->innertext;
                    $imgs = $item->find('div.slides li a[href]');
                    $img_src = [];
                    foreach ($imgs as $img) {
                        $img_src[] = $img->attr['href'];
                    }
                    $dir = $this->downloadImages($img_src);

                    $product = [
                        'name' => $name,
                        'url' => $url,
                        'article' => $article,
                        'price' => $price,
                        'description' => $description,
                        'images' => json_encode($img_src),
                        'dir_img' => $dir
                    ];
                    $this->products[] = $product;
                }

                $alllinks = $html->find('a[href]');
                foreach ($alllinks as $link) {
                    $href = $link->attr['href'];
                    if ($href != null && preg_match('/(\/catalog\/product\/).+/', $href)) {
                        if (preg_match('/\.(png|jpeg|gif|jpg|js|css|xml|pdf)/', $href)
                            || preg_match('/.((%3Bamp%3Bamp)|(sort=)|(set_filter=))/', $href)) {
                            continue;
                        }
                        $href = $this->prepareUrl($href);
                        if (!isset($this->unique_links[$href]) && $this->checkUrlLevel($href)) {
                            $this->unique_links[$href] = 1;
                            $this->worker($href);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            echo 'Error: ' . $url . PHP_EOL;
        }
        $html->clear();
    }

    public function downloadImages($arr)
    {
        $dir = null;
        if (count($arr) > 0) {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . time();
            mkdir($dir, 0777, true);
            foreach ($arr as $item){
                $url = $this->root . $item;
                $ch = curl_init($url);
                $filename = $dir . '/' . basename($item);
                $fp = fopen($filename, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            }
        }
        return $dir;
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
        saveAll($this->links, $overwrite);
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
        if (isset($parts['fragment'])) {
            $url = substr($url, 0, strpos($url, '#'));
        }
        if ($this->del_get && isset($parts['query'])) {
            $url = substr($url, 0, strpos($url, '?'));
        }
        return $url;
    }

    private function checkUrlLevel($url)
    {
        if ($this->all_directions || (!$this->all_directions && strstr($url, $this->start_url))) {
            return true;
        } else return false;
    }
}