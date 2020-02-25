Synology DDNS Cloudflare with multidomains and subdomains
========================

Description
---------------
* A PHP script for Synology DSM which adds Cloudflare DDNS support in your DMS
* Supports multidomains, subdomains and also regional domains (example: dev.my.domain.com.au, domain.com.uk etc)
* Easy instalation process
* Based on CloudFlare API v4

Before start to use this script
---------------
* Have a Cloudflare account with active domains
* Have A Records

Example
---------------
![image](example1.png)

Installation
----------------
1. Activate SSH in DMS (__Control Panel -> Terminal & SNMP -> Enable SSH service__)
![image](example2.png)

2. Connect via SSH and execute command

```
wget https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/cloudflare.php -O /usr/syno/bin/ddns/cloudflare.php && sudo chmod 755 /usr/syno/bin/ddns/cloudflare.php
```

3. Add Cloudflare to the list of DDNS providers DMS file(Location : __/etc.defaults/ddns_provider.conf__)

```
[Cloudflare]
  modulepath=/usr/syno/bin/ddns/cloudflare.php
  queryurl=https://www.cloudflare.com/
```

5. Than go to DMS settingS __Control Panel -> External Access -> DDNS__ and add new DDNS:

* Select Cloudflare as a service provider
* Set in __hostname__ field your list of domains divided by __---__ example: __mydoman.com---subdomain.mydomain.com---vpn.mydomain.com__ or simple: __mydomain.com__
* Set your Cloudflare acoount email into __Username/Email__ field
* Set Cloudflare Global API Key into __Password/Key__ field (See: [https://dash.cloudflare.com/profile/api-tokens](https://dash.cloudflare.com/profile/api-tokens))

![image](example3.png)

6. Enjoy 🍺
