#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

// Normally $argv suffices: $argc seems a bit pointless because amount of arguments & array elements should be same
if ($argc !== 5 || count($argv) != 5) {
    echo Output::INSUFFICIENT_OR_UNKNOWN_PARAMETERS;
    exit();
}

$cf = new updateCFDDNS($argv);
$cf->makeUpdateDNS();

class Output
{
    // Confirmed & logged interpreted/translated messages by Synology
    const SUCCESS = 'good'; // geeft niets? - geeft succesfully registered in logs
    const NO_CHANGES = 'nochg'; // geeft niets? - geeft succesfully registered in logs
    const HOSTNAME_DOES_NOT_EXIST = 'nohost'; // [The hostname specified does not exist. Check if you created the hostname on the website of your DNS provider]
    const HOSTNAME_BLOCKED = 'abuse'; //  [The hostname specified is blocked for update abuse]
    const HOSTNAME_FORMAT_IS_INCORRECT = 'notfqdn'; // [The format of hostname is not correct]
    const AUTHENTICATION_FAILED = 'badauth'; // [Authentication failed]
    const DDNS_PROVIDER_DOWN = '911'; //  [Server is broken][De DDNS-server is tijdelijk buiten dienst. Neem contact op met de Internet-provider.]
    const BAD_HTTP_REQUEST = 'badagent'; //  [DDNS function needs to be modified, please contact synology support]
    const HOSTNAME_FORMAT_INCORRECT = 'badparam'; // [The format of hostname is not correct]

    // Not logged messages, didn't trigger/work while testing on DSM
    const PROVIDER_ADDRESS_NOT_RESOLVED = 'badresolv';
    const PROVIDER_TIMEOUT_CONNECTION = 'badconn';

    // Console only - custom error messages (not triggered by DSM)
    const INSUFFICIENT_OR_UNKNOWN_PARAMETERS = 'Insufficient parameters';
}

/**
 * DDNS auto updater for Synology NAS
 * Base on Cloudflare API v4
 * Supports multidomains and sundomains
 */
class updateCFDDNS
{
    const API_URL = 'https://api.cloudflare.com';
    var $account, $apiKey, $hostList, $ipv4; // argument properties - $ipv4 is provided by DSM itself
    var $ip, $dnsRecordIdList = array(), $ipv6 = false;

    function __construct($argv)
    {
        // Not used: $account ($argv[1]), Used: $apikey ($argv[2]), $hostslist ($argv[3]), $ipv4 ($argv[4])
        $this->apiKey = (string) $argv[2]; // CF Global API Key
        $hostnames = (string) $argv[3]; // example: example.com.uk---sundomain.example1.com---example2.com

        $this->ip = (string) $this->getIpAddressIpify();
        $this->validateIp($this->ip); // Validates or IPV4 or IPV6

        // Test address to force-enable IPV6 manually to simulate ipv6 "found":
//        $this->ipv6 = "2222:7e01::f03c:91ff:fe99:b41d";

        // Since DSM is only providing an IP(v4) address (DSM 6/7 doesn't deliver IPV6)
        // I override above IPV4 detection & rely on DSM instead for now
        $this->validateIp((string) $argv[4]);


        // safer than explode: in case of wrong formatting with --- separations (empty elements removed automatically)
        $arHost = preg_split('/(---)/', $hostnames, -1, PREG_SPLIT_NO_EMPTY);

        // parse each array element to check if every dns hostname is properly formatted, unset any garbage element
        foreach ($arHost as $value) {
            if(!preg_match("/^(?!-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/", $value)) {
                echo Output::HOSTNAME_FORMAT_INCORRECT;
                exit();
            }

            $this->hostList[$value] = [
                'hostname' => '',
                'fullname' => $value,
                'zoneId' => '',
            ];
        }

        $this->setZones();

        foreach ($this->hostList as $arHost) {
            $this->setRecord($arHost, $this->ipv4, 'A');
            if($this->ipv6) {
                $this->setRecord($arHost, $this->ipv6, 'AAAA');
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

        foreach($this->dnsRecordIdList as $recordId => $dnsRecord) {
            $zoneId = $dnsRecord['zoneId'];
            unset($dnsRecord['zoneId']);

            $json = $this->callCFapi("PATCH", "client/v4/zones/${zoneId}/dns_records/${recordId}", $dnsRecord);

            if (!$json['success']) {
                echo 'Update Record failed';
                exit();
            }
        }

        echo Output::SUCCESS;
    }

    function badParam($msg = '')
    {
        echo (strlen($msg) > 0) ? $msg : 'badparam';
        exit();
    }

    /**
     * Evaluates IP address type and assigns to the correct IP property type
     * Only public addresses accessible from the internet are valid options
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
    * Get ip from ipify.org
    * Returns IPV6 address or false boolean in case IP6V is not found
    */
    function getIpAddressIpify() {

        $curlhandle = curl_init();
        curl_setopt($curlhandle, CURLOPT_URL, "https://api64.ipify.org");
        curl_setopt($curlhandle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
        curl_setopt($curlhandle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curlhandle, CURLOPT_TIMEOUT, 30);
        curl_setopt($curlhandle, CURLOPT_VERBOSE, false);
        curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curlhandle);
        curl_close($curlhandle);
        return $result;
    }

    /**
     * Set ZoneID for each hosts
     */
    function setZones()
    {
        $json = $this->callCFapi("GET", "client/v4/zones");
        if (!$json['success']) {
            if(isset($json['errors'][0]['code'])) {
                if($json['errors'][0]['code'] == 9109 || $json['errors'][0]['code'] == 6003) {
                    echo Output::AUTHENTICATION_FAILED;
                    exit();
                }
            }

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
    function setRecord($arHostData, string $ip, $type)
    {
        if (empty($arHostData['fullname'])) {
            return false;
        }

        $fullname = $arHostData['fullname'];

        if (empty($arHostData['zoneId'])) {
            unset($this->hostList[$fullname]);
            return false;
        }

        $zoneId = $arHostData['zoneId'];

        $json = $this->callCFapi("GET", "client/v4/zones/${zoneId}/dns_records?type=${type}&name=${fullname}");

        if (!$json['success']) {
            $this->badParam('unsuccessful response for getRecord host: ' . $fullname);
        }

        if(isset($json['result']['0'])){
            $this->dnsRecordIdList[$json['result']['0']['id']]['type'] = $type;
            $this->dnsRecordIdList[$json['result']['0']['id']]['name'] = $arHostData['fullname'];
            $this->dnsRecordIdList[$json['result']['0']['id']]['content'] = $ip;
            $this->dnsRecordIdList[$json['result']['0']['id']]['zoneId'] = $arHostData['zoneId'];
            $this->dnsRecordIdList[$json['result']['0']['id']]['ttl'] = $json['result']['0']['ttl'];
            $this->dnsRecordIdList[$json['result']['0']['id']]['proxied'] = $json['result']['0']['proxied'];
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