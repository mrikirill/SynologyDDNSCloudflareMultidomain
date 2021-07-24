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
    var $account, $apiKey, $hostList, $ipv4; // argument properties - $ipv4 is provided by DSM itself
    var $ip, $ipv6 = false;

    function __construct($argv)
    {
        // Arguments: account, apikey, hostslist, ipv4 address (DSM 6/7 doesn't deliver IPV6)
        if (count($argv) != 5) {
            $this->badParam('wrong parameter count');
        }

        $this->apiKey = (string) $argv[2]; // CF Global API Key
        $hostname = (string) $argv[3]; // example: example.com.uk---sundomain.example1.com---example2.com

        // Returns either an IPV4 address when IPV6 is unsupported or not found, either returns an IPV6 address,
        // in that case extra steps are necessary because in old version IPV4 won't be set any longer which is not ok
        $this->ip = (string) $this->getIpAddressIpify(); // Can be either IPV4 or IPV6, should serve as IPV6 "detector"
        $this->validateIp($this->ip);

        // Test addresss:
//        $this->ipv6 = "2222:7e01::f03c:91ff:fe99:b41d";

        // Since DSM is standard providing an IPv4 address, we always rely on what DSM is providing, not externally
        $this->validateIp((string) $argv[4]);

        $arHost = explode('---', $hostname);
        if (empty($arHost)) {
            $this->badParam('empty host list');
        }

        foreach ($arHost as $value) {
            $this->hostList[$value] = [
                'hostname' => '',
                'fullname' => $value,
                'zoneId' => '',
                'recordIdA' => '',
                'recordIdAAAA' => '',
                'proxied' => true,
                'A' => false,
                'AAAA' => false,
            ];
        }

        $this->setZones();

        foreach ($this->hostList as $arHost) {
            $this->setRecord($arHost['fullname'], $arHost['zoneId'], 'A');
            if($this->ipv6) {
                $this->setRecord($arHost['fullname'], $arHost['zoneId'], 'AAAA');
            }
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
            if($arHost['AAAA']) {
                $post = [
                    'type' => 'AAAA',
                    'name' => $arHost['fullname'],
                    'content' => $this->ipv6,
                    'ttl' => 1,
                    'proxied' => $arHost['proxied'],
                ];

                $json = $this->callCFapi("PATCH", "client/v4/zones/" . $arHost['zoneId'] . "/dns_records/" . $arHost['recordIdAAAA'], $post);

                if (!$json['success']) {
                    echo 'Update Record failed';
                    exit();
                }
            }

            if($arHost['A']) {
                $post = [
                    'type' => 'A',
                    'name' => $arHost['fullname'],
                    'content' => $this->ipv4,
                    'ttl' => 1,
                    'proxied' => $arHost['proxied'],
                ];

                $json = $this->callCFapi("PATCH", "client/v4/zones/" . $arHost['zoneId'] . "/dns_records/" . $arHost['recordIdA'], $post);

                if (!$json['success']) {
                    echo 'Update Record failed';
                    exit();
                }
            }
        }
        echo "good";
    }

    function badParam($msg = '')
    {
        echo (strlen($msg) > 0) ? $msg : 'badparam';
        exit();
    }

    /**
     * Evaluates IP address type and asssigns to the correct IP property type
     * Only public addresses accessible from the internet are valid
     *
     * @param $ip
     * @return bool
     */
    function validateIp($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )) {
            $this->ipv6 = $ip;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )) {
            $this->ipv4 = $ip;
        } else {
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
     * Set A Records for each host
     */
    function setRecord($fullname, $zoneId, $type)
    {
        if (empty($fullname)) {
            return false;
        }

        if (empty($zoneId)) {
            unset($this->hostList[$fullname]);
            return false;
        }

        $json = $this->callCFapi("GET", "client/v4/zones/${zoneId}/dns_records?type=${type}&name=${fullname}");

        if (!$json['success']) {
            $this->badParam('unsuccessful response for getRecord host: ' . $fullname);
        }

        // In case there's an A but no AAAA record set-up (or opposite) on Cloudflare, it may result in an error
        if(isset($json['result']['0'])){
            $this->hostList[$fullname]['proxied'] = $json['result']['0']['proxied'];
            if($json['result']['0']['type'] === 'AAAA') {
                $this->hostList[$fullname]['AAAA'] = $this->ipv6;
                $this->hostList[$fullname]['recordIdAAAA'] = $json['result']['0']['id'];
            }
            if($json['result']['0']['type'] === 'A') {
                $this->hostList[$fullname]['A'] = $this->ipv4;
                $this->hostList[$fullname]['recordIdA'] = $json['result']['0']['id'];
            }
        }
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
            case "PATCH":
                $options[CURLOPT_POST] = false;
                $options[CURLOPT_HTTPGET] = false;
                $options[CURLOPT_CUSTOMREQUEST] = "PATCH";
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
