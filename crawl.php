<?php

require_once "vendor/autoload.php";
function dd($o)
{
    dump($o);
    die(0);
}

function curl($data)
{
    $data = http_build_query($data);
    $core = "curl 'https://forms.iebc.or.ke/documents' -H 'origin: https://forms.iebc.or.ke' -H 'content-type: application/x-www-form-urlencoded' -H 'cookie:laravel_session=eyJpdiI6InZhaFhjcWMyYmJWVXpPZVlrYlB5dmc9PSIsInZhbHVlIjoiWGh3ZHB0MlpBaUx4WmZsc1JhRGFBckg2MXJWcHc4TDE5VjRKTTc5S1N1OFM3cWdDOHpMSFpoRHJ2THh3K0Zja3N5dXVsTmZjVGFHUmpPdlZ5cnJrREE9PSIsIm1hYyI6IjRlNzI3MTNjY2I4N2U2OTFhNmFiMDhiNDIxOTU4Mjc0NzJkOTIwZmNlNWRkNTBhZjI0YTZlNGI5MjNjODEzMjYifQ%3D%3D; AWSALB=DrH+sPiPbfTdjnK7IdXF8oJfWySwIZLM3aDmxswf/ulwG53W79VG5DB1hoE93Z0m6mgEg7Q/36dRiLxm8u+nmCfSenJ8NMpVh/yHKbNMZbJcb+h+9NJufzi1+Sl9' --data '$data' -s --compressed";
    return shell_exec($core);
}

function save($dir, $url, $name)
{
    $path = "$dir/$name.jpg";
    if (!file_exists($path)) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, file_get_contents($url));
    }
}

use Goutte\Client;

$client = new Client();
$guzzle = new GuzzleHttp\Client(['http_errors' => false]);

echo "Loading base form...\r";
$crawler = $client->request('GET', 'https://forms.iebc.or.ke');
echo "Loaded base form\r";

$values = $crawler->selectButton('View')->form()['county_id']->availableOptionValues();

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
    $constituencies = json_decode(file_get_contents("https://forms.iebc.or.ke/constituency/$id"), true);
    echo("Processing $county\r");
    foreach ($constituencies['constituency'] as $c_id => $constituency) {
        echo("Processing $county/$constituency\r");
        $wards = json_decode(file_get_contents("https://forms.iebc.or.ke/wards/$c_id"), true);
        foreach ($wards['wards'] as $w_id => $ward) {
            echo("Processing $county/$constituency/$ward\r");
            $centers = json_decode(file_get_contents("https://forms.iebc.or.ke/pollingcentre/$w_id"), true);
            foreach ($centers['polling_centre'] as $p_id => $centre) {
                echo("Processing $county/$constituency/$ward/$centre\r");
                $stations = json_decode(file_get_contents("https://forms.iebc.or.ke/pollingstation/$p_id-$w_id"), true);
                foreach ($stations['polling_station'] as $s_id => $station) {
                    $station .= " $s_id";
                    $name = "$county/$constituency/$ward/$centre";
                    echo("Processing $name/$station\n");
                    if (!file_exists("forms/34A/$name/$station.jpg")) {
                        $token = "8GcGUIauFdNFIk1KTq5VMwCOvXEx9Qav0An68lJU";
                        $html = curl(['county_id' => $id, 'const_id' => $c_id, 'ward_id' => $w_id, '_token' => $token, 'pcentre_id' => $p_id, 'pstation_id' => $s_id]);
                        $crawler = new \Symfony\Component\DomCrawler\Crawler();
                        $crawler->addContent($html);
                        $crawler->filter('#home > div > div > div:nth-child(6) > h4:nth-child(5) > a')->each(function ($node) use ($name, $station) {
                            save("forms/34A/$name", "https://forms.iebc.or.ke" . $node->attr('href'), "$station");
                        });
                    }
                }
            }
        }
    }
}