
# Synology Dynamic DNS with Cloudflare for both multidomains and subdomains

## Table of contents

* [Update note](#update-note)
* [Default Cloudflare ports](#default-cloudflare-ports)
* [What this script does](#what-this-script-does)
* [Before you begin](#before-you-begin)
* [Example](#example)
* [How to install](#how-to-install)
* [Troubleshooting](#troubleshooting)
    + [Error returned, such as 1020 error; or update attempt not showing in Cloudflare Audit logs?](#error-returned--such-as-1020-error--or-update-attempt-not-showing-in-cloudflare-audit-logs-)

## Update note

*Reinstall may be required after updating DSM*
After updates to Synology DSM, you may find the file __/usr/syno/bin/ddns/cloudflare.php__ has been deleted, and __/etc.defaults/ddns_provider.conf__ reset to default settings.

If this occurs, simply repeat the installations steps below.

## Default Cloudflare ports

|HTTP ports supported by Cloudflare  | HTTPS ports supported by Cloudflare |
|--|--|
| 80 | 443 |
| 8080 | 2053 | 
| 8880 | 2083 |
| 2052 | 2087 | 
| 2082 | 2096 |
| 2086 | 8443 | 
| 2095 | |


## What this script does

* A PHP script for Synology DSM (and potentially Synology SRM devices) adding support for Cloudflare to Network Centre > Dynamic DNS (DDNS).
* Supports single domains, multidomains, subdomains and regional domains, or any combination thereof (example: dev.my.domain.com.au, domain.com.uk etc)
* Easy instalation process
* Based on CloudFlare API v4

## Before you begin

Before starting the installation process, make sure you have (and know) the following information, or have completed these steps:

 1. *Cloudflare credentials*
	 a. Know your Cloudflare account username (or [register for an account if you're new to Cloudflare](https://dash.cloudflare.com/sign-up)); and
	 b. Have your [Global API key](https://dash.cloudflare.com/profile/api-tokens) (More info: [Global API keys](https://support.cloudflare.com/hc/en-us/articles/200167836-Managing-API-Tokens-and-Keys)).
 2. *DNS settings:*
	 Ensure the DNS A record(s) for the domain/zone(s) you wish to update with this script have been created (More information: [Managing DNS records](https://support.cloudflare.com/hc/en-us/articles/360019093151-Managing-DNS-records-in-Cloudflare))

## Example

![image](example1.png)

## How to install

1. **Activate SSH on your supported device:**
	 a. For DSM Users: Navigate to __Control Panel > Terminal & SNMP > Enable SSH service__
	 b. For SRM users: Navigate to __Control Panel > Services > System Services > Terminal > Enable SSH service__
	 
	![image](example2.png)

2. **Connect via SSH:** Connect to your supported device via SSH and execute command

* For DSM Users
  ```
  wget https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/cloudflare.php -O /usr/syno/bin/ddns/cloudflare.php && sudo chmod 755 /usr/syno/bin/ddns/cloudflare.php
  ```

* For SRM Users
  Note: Ensure you are connected as root in your SSH session
  ```
  wget https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/cloudflare.php -O /usr/syno/bin/ddns/cloudflare.php && chmod 755 /usr/syno/bin/ddns/cloudflare.php
  ```

	**Note:** For SRM users, you must connect to your device as root. No other username will allow these commands to run.

3. **Update DDNS provider list:** Using your preferred/available command line editor, insert the text below to your DMS file (Location : __/etc.defaults/ddns_provider.conf__), to add DDNS support via Cloudflare:

	```
	[Cloudflare]
	  modulepath=/usr/syno/bin/ddns/cloudflare.php
	  queryurl=https://www.cloudflare.com/
	 ```

	 Note: For SRM users, break out this [Vim cheat sheet](https://coderwall.com/p/adv71w/basic-vim-commands-for-getting-started), as it's the only text editor available to you.
 
4. **Update your DDNS settings:** 

	 a. *For DSM Users:* Navigate to __Control Panel > External Access > DDNS__ then add new DDNS
	 b. *For SRM users:* Navigate to __Network Centre > Internet > QuickConnect & DDNS > DDNS__ and press the Add button:

	Add/Update the DDNS settings screen as follows:

	* Service provider: Select Cloudflare
	* Hostname:
 For a single domain: __mydomain.com__
For multiple domains: __subdomain.mydomain.com---vpn.mydomain.com__
	(ensure each domain is seperated by three dashes: ---)
	* Username: The email address you use for logging in to Cloudflare.
	* Password: Your Cloudflare Global API Key

	![image](example3.png)

	Finally, press the test connection button to confirm all information is correctly entered, before pressing Ok to save and confirm your details.

5. Enjoy 🍺 and __don't forget to deactivate SSH (step 1) if you don't need it__.

## Troubleshooting

### Error returned, such as 1020 error; or update attempt not showing in Cloudflare Audit logs?
That generally means you've not entered something correctly in the DDNS screen for your domain.

Revisit Step 4 in How to install, and make sure everything is correctly entered.

And you can also check your Cloudflare Audit logs to see what - if anything - has made it there with your API key (More information: [Understanding Cloudflare Audit Logs](https://support.cloudflare.com/hc/en-us/articles/115002833612-Understanding-Cloudflare-Audit-Logs). Updates using the API will appear in the Audit logs as a Rec Set action.
