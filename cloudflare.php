#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

if ($argc !== 5) {
    echo 'badparam';
    exit();
}

$cf = new updateCFDDNS($argv);
$cf->makeUpdateDNS();

/**
 * DDNS auto updater for Synology NAS
 * Base on Cloudflare API v4
 * Supports multidomains and sundomains
 */
class updateCFDDNS
{
    const API_URL = 'https://api.cloudflare.com';
    var $account, $apiKey, $hostList, $ip;

    function __construct($argv)
    {
        if (count($argv) != 5) {
            $this->badParam('wrong parameter count');
        }

        $this->apiKey = (string) $argv[2]; // CF Global API Key
        $hostname = (string) $argv[3]; // example: example.com.uk---sundomain.example1.com---example2.com
        $this->ip = (string) $this->getIpAddressIpify();

        $this->validateIp($this->ip);

        $arHost = explode('---', $hostname);
        if (empty($arHost)) {
            $this->badParam('empty host list');
        }

        foreach ($arHost as $value) {
            $this->hostList[$value] = [
                'hostname' => '',
                'fullname' => $value,
                'zoneId' => '',
                'recordId' => '',
                'proxied' => true,
            ];
        }

        $this->setZones();
        foreach ($this->hostList as $arHost) {
            $this->setRecord($arHost['fullname'], $arHost['zoneId']);
        }
    }

    /**
     * Update CF DNS records 
     */
    function makeUpdateDNS()
    {
        if(empty($this->hostList)) {
            $this->badParam('empty host list');
        }

        foreach ($this->hostList as $arHost) {            
            $post = [
                'type' => $this->getZoneTypeByIp($this->ip),
                'name' => $arHost['fullname'],
                'content' => $this->ip,
                'ttl' => 1,
                'proxied' => $arHost['proxied'],
            ];

            $json = $this->callCFapi("PUT", "client/v4/zones/" . $arHost['zoneId'] . "/dns_records/" . $arHost['recordId'], $post);
            if (!$json['success']) {
                echo 'Update Record failed';
                exit();
            }
        }
        echo "good";
    }

    function badParam($msg = '')
    {
        echo (strlen($msg) > 0) ? $msg : 'badparam';
        exit();
    }

    function validateIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->badParam('invalid ip-address');
        }
        return true;
    }
    /*
    * get ip from ipify.org
    */
    function getIpAddressIpify() {
        return file_get_contents('https://api64.ipify.org');
    }

    /*
    * IPv4 = zone A, IPv6 = zone AAAA
    * @link https://www.cloudflare.com/en-au/learning/dns/dns-records/dns-a-record/
    */
    function getZoneTypeByIp($ip) {
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'AAAA';
        }
        return 'A';        
    }

    /**
     * Set ZoneID for each hosts
     */
    function setZones()
    {
        $json = $this->callCFapi("GET", "client/v4/zones");
        if (!$json['success']) {
            $this->badParam('getZone unsuccessful response');
        }
        $arZones = [];
        foreach ($json['result'] as $ar) {
            $arZones[] = [
                'hostname' => $ar['name'],
                'zoneId' => $ar['id']
            ];
        }

        foreach ($this->hostList as $hostname => $arHost) {
            $res = $this->isZonesContainFullname($arZones, $arHost['fullname']);
            if(!empty($res)) {
                $this->hostList[$hostname]['zoneId'] = $res['zoneId'];
                $this->hostList[$hostname]['hostname'] = $res['hostname'];
            }
        }
    }

    /**
     * Find hostname for full domain name
     * example: domain.com.uk --> vpn.domain.com.uk
     */
    function isZonesContainFullname($arZones, $fullname){
        $res = [];
        foreach($arZones as $arZone) {
            if (strpos($fullname, $arZone['hostname']) !== false) {
                $res = $arZone;
                break;
            }
        }
        return $res;
    }

    /**
     * Set Records for each hosts
     */
    function setRecord($fullname, $zoneId)
    {
        if (empty($fullname)) {
            return false;
        }

        if (empty($zoneId)) {
            unset($this->hostList[$fullname]);
            return false;
        }

        $json = $this->callCFapi("GET", "client/v4/zones/${zoneId}/dns_records?type=A&name=${fullname}");
        if (!$json['success']) {
            $this->badParam('unsuccessful response for getRecord host: ' . $fullname);
        }
        $this->hostList[$fullname]['recordId'] = $json['result']['0']['id'];
        $this->hostList[$fullname]['proxied'] = $json['result']['0']['proxied'];
    }

    /**
     * Call CloudFlare v4 API @link https://api.cloudflare.com/#getting-started-endpoints
     */
    function callCFapi($method, $path, $data = []) {
        $options = [
            CURLOPT_URL => self::API_URL . '/' . $path,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $this->apiKey", "Content-Type: application/json"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
        ];

        if(empty($method)){
            $this->badParam('Empty method');
        }

        switch($method) {
            case "GET":
                $options[CURLOPT_HTTPGET] = true;
            break;

            case "POST":
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_HTTPGET] = false;
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            break;

            case "PUT":
                $options[CURLOPT_POST] = false;
                $options[CURLOPT_HTTPGET] = false;
                $options[CURLOPT_CUSTOMREQUEST] = "PUT";
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            break;
        }

        $req = curl_init();
        curl_setopt_array($req, $options);
        $res = curl_exec($req);
        curl_close($req);
        return json_decode($res, true);
    }
}
?>