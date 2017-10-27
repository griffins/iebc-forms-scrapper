<?php

require_once "vendor/autoload.php";
function dd($o)
{
    dump($o);
    die(0);
}

function get_via_cache($url)
{
    $path = __DIR__ . '/.cache/' . sha1($url);
    if (file_exists($path)) {
        return file_get_contents($path);
    } else {
        $cache = file_get_contents($url);
        if (!file_exists(__DIR__ . '/.cache/')) {
            mkdir(__DIR__ . '/.cache/', 0777, true);
        }
        file_put_contents($path, $cache);
        return $cache;
    }
}

function curl($data, $cookie)
{
    $data = http_build_query($data);
    $core = "curl 'https://forms.iebc.or.ke/documents' -H 'origin: https://forms.iebc.or.ke' -H 'content-type: application/x-www-form-urlencoded' -H 'cookie: $cookie' --data '$data' -s --compressed";
    return shell_exec($core);
}

function save($dir, $url, $name)
{
    $path = "$dir/$name.jpg";
    if (!file_exists($path)) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
//        echo "Downloading file: $url\n";
        file_put_contents($path, file_get_contents($url));
    }
}

use Goutte\Client;

$guzzle = new GuzzleHttp\Client(['http_errors' => false]);
$jar = new \Symfony\Component\BrowserKit\CookieJar();
$client = new Client([], null, $jar);

echo "Loading base form...\r";
$crawler = $client->request('GET', 'https://forms.iebc.or.ke');

$cookies_ = $jar->allValues("https://forms.iebc.or.ke", true);
$cookies = "";
foreach ($cookies_ as $name => $cookie) {
    $cookies .= $name . "=" . $cookie . ";";
}

echo "Loaded base form\r";

$values = $crawler->selectButton('View')->form()['county_id']->availableOptionValues();
$token = $crawler->selectButton('View')->form()['_token']->getValue();

$counties_ = $crawler->filter('#county')->filter('option')->each(function ($node) {
    return $node->text();
});
unset($crawler);
$counties = [];

foreach ($counties_ as $id => $value) {
    $counties[$values[$id]] = $counties_[$id];
}

foreach ($counties as $id => $county) {
    if ($id == "") {
        continue;
    }
    $constituencies = json_decode(get_via_cache("https://forms.iebc.or.ke/constituency/$id"), true);
    echo("Processing $county\r");
    foreach ($constituencies['constituency'] as $c_id => $constituency) {
        echo("Processing $county/$constituency\r");
        $wards = json_decode(get_via_cache("https://forms.iebc.or.ke/wards/$c_id"), true);
        foreach ($wards['wards'] as $w_id => $ward) {
            echo("Processing $county/$constituency/$ward\r");
            $centers = json_decode(get_via_cache("https://forms.iebc.or.ke/pollingcentre/$w_id"), true);
            foreach ($centers['polling_centre'] as $p_id => $centre) {
                $stations = json_decode(get_via_cache("https://forms.iebc.or.ke/pollingstation/$p_id-$w_id"), true);
                foreach ($stations['polling_station'] as $s_id => $station) {
                    $station .= " $s_id";
                    $name = "$county/$constituency/$ward/$centre";
                    if (!file_exists("forms/34A/$name/$station.jpg")) {
                        echo("Processing $name/$station\n");
                        $html = curl(['county_id' => $id, 'const_id' => $c_id, 'ward_id' => $w_id, '_token' => $token, 'pcentre_id' => $p_id, 'pstation_id' => $s_id], $cookies);
                        $crawler = new \Symfony\Component\DomCrawler\Crawler();
                        $crawler->addContent($html);
                        $crawler->filter('#home > div > div > div:nth-child(6) > h4:nth-child(5) > a')->each(function ($node) use ($name, $station) {
//                            echo "Found image: " . $node->attr('href') . "\n";
                            save("forms/34A/$name", "https://forms.iebc.or.ke" . $node->attr('href'), "$station");
                        });
                    } else {
                        echo("Skipping $name/$station\n");
                    }
                }
            }
        }
    }
}