#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php
/**
 * Cloudflare DDNS agent for Synology DSM
 * @link https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain
 * @version 2.0
 * @license MIT
 * @author https://github.com/mrikirill
 */

// Note: username - $argv[1], password - $argv[2], hostname - $argv[3], ipv4 - $argv[4]
if ($argc !== 5 || count($argv) != 5) {
    echo SynologyOutput::BAD_PARAMS;
    exit();
}

/**
 * Note:
 * Cloudflare API Key - $argv[2]
 * Hostname - $argv[1] we use username as hostname source cause it supports 256 symbols
*/
$cf = new SynologyCloudflareDDNSAgent($argv[2], $argv[1]);
$cf->setDnsRecords();
$cf->updateDnsRecords();

class SynologyOutput
{
    const SUCCESS = 'good';               // Update successfully
    const NO_HOSTNAME = 'nohost';         // The hostname specified does not exist in this user account
    const HOSTNAME_INCORRECT = 'notfqdn'; // The hostname specified is not a fully-qualified domain name
    const AUTH_FAILED = 'badauth';        // Authenticate failed
    const DDNS_FAILED = '911';            // There is a problem or scheduled maintenance on provider side
    const BAD_HTTP_REQUEST = 'badagent';  // HTTP method/parameters is not permitted
    const BAD_PARAMS = 'badparam';        // Bad params
}

/**
 * Cloudflare api client
 * @link https://developers.cloudflare.com/api/
 */
class CloudflareAPI
{
    const API_URL = 'https://api.cloudflare.com';
    const ZONES_PER_PAGE = 50;
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Makes an API call to the specified Cloudflare endpoint.
     *
     * @param string $method The HTTP method to use (GET, POST, PUT, PATCH).
     * @param string $path The API endpoint path to call.
     * @param array $data Optional data to send with the request.
     * @return array The JSON-decoded response from the API call.
     * @throws Exception If an error occurs during the API call.
     */
    private function call($method, $path, $data = [])
    {
        $options = [
            CURLOPT_URL => self::API_URL . '/' . $path,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $this->apiKey", "Content-Type: application/json"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        switch ($method) {
            case "GET":
                $options[CURLOPT_HTTPGET] = true;
                break;
            case "POST":
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case "PUT":
                $options[CURLOPT_CUSTOMREQUEST] = "PUT";
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case "PATCH":
                $options[CURLOPT_CUSTOMREQUEST] = "PATCH";
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            default:
                throw new Exception("Invalid HTTP method: $method");
        }

        $req = curl_init();
        curl_setopt_array($req, $options);
        $res = curl_exec($req);

        if (curl_errno($req)) {
            throw new Exception('Curl error: ' . curl_error($req));
        }

        curl_close($req);
        $json = json_decode($res, true);

        if (!$json['success']) {
            throw new Exception('API call failed');
        }

        return $json;
    }

    /**
     * @link https://developers.cloudflare.com/api/operations/user-api-tokens-verify-token
     * @throws Exception
     */
    public function verifyToken()
    {
        return $this->call("GET", "client/v4/user/tokens/verify");
    }

    /**
     * Note: getting max 50 zones see the documentation
     * @link https://developers.cloudflare.com/api/operations/zones-get
     * @throws Exception
     */
    public function getZones()
    {
        return $this->call("GET", "client/v4/zones?per_page=" . self::ZONES_PER_PAGE . "&status=active");
    }

    /**
     * @link https://developers.cloudflare.com/api/operations/dns-records-for-a-zone-list-dns-records
     * @throws Exception
     */
    public function getDnsRecords($zoneId, $type, $name)
    {
        return $this->call("GET", "client/v4/zones/$zoneId/dns_records?type=$type&name=$name");
    }

    /**
     * @link https://developers.cloudflare.com/api/operations/dns-records-for-a-zone-patch-dns-record
     * @throws Exception
     */
    public function updateDnsRecord($zoneId, $recordId, $body)
    {
        return $this->call("PATCH", "client/v4/zones/$zoneId/dns_records/$recordId", $body);
    }
}

class Ipify
{
    const API_URL = 'https://api64.ipify.org';
    /**
     * Universal: IPv4/IPv6
     * @link https://www.ipify.org
     * @throws Exception
     */
    public function getExternalIpAddress()
    {
        $options = [
            CURLOPT_URL => self::API_URL . "/?format=json",
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_HTTPGET => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        $req = curl_init();
        curl_setopt_array($req, $options);
        $res = curl_exec($req);

        if (curl_errno($req)) {
            throw new Exception('Curl error: ' . curl_error($req));
        }

        curl_close($req);
        $json = json_decode($res, true);

        if (!$json['ip']) {
            throw new Exception('API call failed: ' . json_encode($json));
        }

        return $json['ip'];
    }
}

class DnsRecordEntity
{
    public $id;
    public $type;
    public $hostname;
    public $ip;
    public $zoneId;
    public $ttl;
    public $proxied;

    public function __construct($id, $type, $hostname, $ip, $zoneId, $ttl, $proxied)
    {
        $this->id = $id;
        $this->type = $type;
        $this->hostname = $hostname;
        $this->ip = $ip;
        $this->zoneId = $zoneId;
        $this->ttl = $ttl;
        $this->proxied = $proxied;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->hostname,
            'content' => $this->ip,
            'zoneId' => $this->zoneId,
            'ttl' => $this->ttl,
            'proxied' => $this->proxied
        ];
    }
}

/**
 * DDNS auto update agent for Synology DSM
 * Supports multidomains and subdomains
 */
class SynologyCloudflareDDNSAgent
{
    private $ipv4, $ipv6, $dnsRecordList = [];
    private $cloudflareAPI;
    private $ipify;

    function __construct($apiKey, $hostname)
    {
        $this->cloudflareAPI = new CloudflareAPI($apiKey);
        $this->ipify = new Ipify();

        try {
            $ip = $this->ipify->getExternalIpAddress();
            switch ($this->getIpAddressVersion($ip)) {
                case 4:
                    $this->ipv4 = $ip;
                    break;
                case 6:
                    $this->ipv6 = $ip;
                    break;
            }
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            $this->exitWithSynologyMsg();
        }

        try {
            if (!$this->isCFTokenValid()) {
                $this->exitWithSynologyMsg(SynologyOutput::AUTH_FAILED);
            }
        } catch (Exception $e) {
            $this->exitWithSynologyMsg();
        }

        $hostnameList = $this->extractHostnames($hostname);
        if (empty($hostnameList)) {
            $this->exitWithSynologyMsg(SynologyOutput::HOSTNAME_INCORRECT);
        }

        $this->matchHostnameWithZone($hostnameList);
    }

    /**
     * Sets DNS A Records for each host in the DNS record list.
     *
     * Iterates over the dnsRecordList, retrieves existing DNS records
     * from the Cloudflare API, and updates the records' IDs, TTL, and proxied status.
     *
     * If the dnsRecordList is empty, it exits with a NO_HOSTNAME message.
     * If an API call fails, it exits with a DDNS_FAILED message.
     */
    public function setDnsRecords()
    {
        if (empty($this->dnsRecordList)) {
            $this->exitWithSynologyMsg(SynologyOutput::NO_HOSTNAME);
        }

        try {
            foreach ($this->dnsRecordList as &$dnsRecord) {
                $json = $this->cloudflareAPI->getDnsRecords($dnsRecord->zoneId, $dnsRecord->type, $dnsRecord->hostname);
                if (isset($json['result']['0'])) {
                    $dnsRecord->id = $json['result']['0']['id'];
                    $dnsRecord->ttl = $json['result']['0']['ttl'];
                    $dnsRecord->proxied = $json['result']['0']['proxied'];
                }
            }
        } catch (Exception $e) {
            $this->exitWithSynologyMsg(SynologyOutput::DDNS_FAILED);
        }
    }

    /**
     * Updates Cloudflare DNS records
     *
     * Verifies each DNS record in the list, attempts to update it via the Cloudflare API,
     * and outputs 'SUCCESS' if all updates are completed without errors.
     * If the DNS record list is empty, it exits with a 'NO_HOSTNAME' message.
     * If an API call fails, it exits with a 'BAD_HTTP_REQUEST' message.
     */
    function updateDnsRecords()
    {
        if (empty($this->dnsRecordList)) {
            $this->exitWithSynologyMsg(SynologyOutput::NO_HOSTNAME);
        }
        foreach ($this->dnsRecordList as $dnsRecord) {
            try {
               $this->cloudflareAPI->updateDnsRecord($dnsRecord->zoneId, $dnsRecord->id, $dnsRecord->toArray());
            } catch (Exception $e) {
                $this->exitWithSynologyMsg(SynologyOutput::BAD_HTTP_REQUEST);
            }
        }

        echo SynologyOutput::SUCCESS;
    }

    /**
     * Matches hostnames with their corresponding Cloudflare zone.
     *
     * This method fetches the list of zones from the Cloudflare API,
     * iterates over each hostname provided, and stores corresponding DNS records
     * in the dnsRecordList property if a match is found.
     *
     * @param array $hostnameList List of hostnames to be matched with zones.
     * @throws Exception If an error occurs during the API call,
     * it outputs an appropriate Synology message and exits the script.
     */
    private function matchHostnameWithZone($hostnameList = [])
    {
        try {
            $zoneList = $this->cloudflareAPI->getZones();
            $zoneList = $zoneList['result'];
            foreach ($zoneList as $zone) {
                $zoneId = $zone['id'];
                $zoneName = $zone['name'];
                foreach ($hostnameList as $hostname) {
                    if (strpos($hostname, $zoneName) !== false) {
                        $this->dnsRecordList[$hostname] = new DnsRecordEntity(
                            '',
                            $this->getDnsRecordType(),
                            $hostname,
                            $this->getIpAddress(),
                            $zoneId,
                            '',
                            ''
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->exitWithSynologyMsg(SynologyOutput::NO_HOSTNAME);
        }
    }

    /**
     * Determines the DNS record type (A or AAAA) based on the IP version.
     * Returns 'AAAA' if IPv6 is present, or 'A' if only IPv4 is available.
     *
     * @return string The DNS record type, either 'A' or 'AAAA'.
     */
    private function getDnsRecordType()
    {
        return $this->ipv6 ? 'AAAA' : 'A';
    }

    /**
     * Retrieves the current external IP address.
     * If both IPv4 and IPv6 are available, returns the IPv6 address.
     *
     * @return string The current external IP address, either IPv4 or IPv6.
     */
    private function getIpAddress()
    {
        return $this->ipv6 ? $this->ipv6 : $this->ipv4;
    }

    /**
     * Extracts valid hostnames from a given string of hostnames separated by pipes (|).
     *
     * @param string $hostnames A string of hostnames separated by pipes (|).
     * @return array An array of validated and parsed hostnames.
     */
    private function extractHostnames($hostnames)
    {
        $arHost = preg_split('/\|/', $hostnames, -1, PREG_SPLIT_NO_EMPTY);
        $hostList = [];
        foreach ($arHost as $value) {
            if ($this->isValidHostname($value)) {
                $hostList[] = $value;
            }
        }
        return $hostList;
    }

    /**
     * Validates whether a given value is a fully-qualified domain name (FQDN).
     *
     * Uses a regular expression pattern to check for valid FQDN structure.
     * An FQDN must consist of at least one label, each label must be alphanumeric or hyphenated,
     * but cannot begin or end with a hyphen, followed by a top-level domain (TLD) that is 2-6 characters long.
     *
     * @param string $value The input string to be validated as an FQDN.
     * @return bool Returns true if the input string is a valid FQDN, false otherwise.
     */
    private function isValidHostname($value)
    {
        $domainPattern = "/^(?!-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/";
        return preg_match($domainPattern, $value);
    }

    /**
     * Checks CF API Token is valid
     *
     * This function verifies if the Cloudflare API token is valid by calling the verifyToken
     * method of the CloudflareAPI class. If the token is valid, it returns true.
     * If an exception occurs during the verification process, the function catches the exception
     * and returns false, indicating that the token is not valid or an error has occurred.
     *
     * @return bool Returns true if the Cloudflare API token is valid, otherwise false.
     */
    private function isCFTokenValid()
    {
        try {
            $res = $this->cloudflareAPI->verifyToken();
            return $res['success'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Outputs a message and exits the script.
     *
     * This function is used to print a specified message and then terminate
     * the execution of the script. It is primarily used for handling
     * and responding to various error conditions during the DNS update process.
     *
     * @param string $msg The message to be output before exiting.
     * If no message is specified, an empty string is printed.
     */
    private function exitWithSynologyMsg($msg = '')
    {
        echo $msg;
        exit();
    }

    /**
     * Determines the IP address version (IPv4 or IPv6) of a given IP address.
     *
     * This method checks if the provided IP address is a valid, public IPv6 or IPv4 address.
     *
     * @param string $ip The IP address to be evaluated.
     * @return int Returns 6 if the IP address is a valid IPv6 address, 4 if it is a valid IPv4 address.
     * @throws InvalidArgumentException if the IP address is not valid or is not public.
     */
    private function getIpAddressVersion($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 6;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 4;
        } else {
            throw new InvalidArgumentException('Invalid IP address');
        }
    }
}
?>