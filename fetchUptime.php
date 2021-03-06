<?php
require_once 'config.php';
$login = file(CREDENTIALS_FILE, FILE_IGNORE_NEW_LINES);
$curl = curl_init();
$m = new \Memcached();
$m->addServer("localhost",11211);
$out = array();
$to = time();
$from = strtotime("-30 days");

$hosts = array(
    "Atika Backoffice" => 615766,
    "DrVideo Encoding" => 615772,
    "DrFront Backoffice" => 615760,
    "DrVideo Backoffice" => 615764,
    "DrVideo CDN" => 615768,
    "DrVideo API" => 615770,
    "DrPublish Backoffice" => 615767,
    "DrPublish API" => 615771);

foreach ($hosts as $hostName => $hostID) {

    $options = array(
        CURLOPT_URL => "https://api.pingdom.com/api/2.0/summary.outage/$hostID?from=$from&to=$to",
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_USERPWD => $login[0].":".$login[1],
        CURLOPT_HTTPHEADER => array("App-Key: ".$login[2]),
        CURLOPT_RETURNTRANSFER => true);

    curl_setopt_array($curl,$options);
    $response = json_decode(curl_exec($curl),true);
    $checkList = $response["summary"]["states"];

    foreach ($checkList as $check) {

        if (!isset($out[$hostName][date("d/m/Y",$check["timefrom"])]["Downtime"])) {
            $out[$hostName][date("d/m/Y",$check["timefrom"])]["Downtime"] = 0;
        }

        if ($check["status"] != "up") {

            $out[$hostName][date("d/m/Y",$check["timefrom"])]["Downtime"] += $check["timeto"] - $check["timefrom"];

        }
    }
}

$m->set("uptime", $out, 43200);
