
# Synology Dynamic DNS with Cloudflare for both multidomains and subdomains

> Documentation website: https://mrikirill.github.io/SynologyDDNSCloudflareMultidomain/

## Table of contents

* 🆕 [What is new](#what-is-new)
* [What this script does](#what-this-script-does)
* [Before you begin](#before-you-begin)
* 🆕 [How to install](#how-to-install)
* [Troubleshooting and known issues](#troubleshooting-and-known-issues)
  + [CloudFlare API free domains limitation](#cloudflare-api-free-domains-limitation)
  + [Connection test failed or error returned](#connection-test-failed-or-error-returned)
  + [Cloudflare no longer listed as a DDNS provider after a DSM update](#cloudflare-no-longer-listed-as-a-ddns-provider-after-dsm-or-srm-updates)
* [Default Cloudflare ports](#default-cloudflare-ports)
* [Debug script](#debug)

## What is new

- 🆕 New hostname input format: `subdomain1.mydomain.com|subdomain2.mydomain.com` (each domain is separated: `|`) used to be with `---` separator
- 🆕 Hostname input uses a new source of data (account) and support 256 symbols limit (DSM UI limit)
- 🆕 Autodetect IPv4 and IPv6 addresses
- 🆕 Optimised request to Cloudflare API
- 🆕 Installer script

## What this script does

* A PHP script for Synology DSM (and potentially Synology SRM devices) adding support for Cloudflare to Network Centre > Dynamic DNS (DDNS).
* Supports single domains, multidomains, subdomains and regional domains, or any combination thereof (example: dev.my.domain.com.au, domain.com.uk etc)
* 🆕 Easy installation process (added auto install script)
* Based on CloudFlare API v4
* [Supports dual stack IPv4 and IPv6](https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/pull/13)

## Before you begin

Before starting the installation process, make sure you have (and know) the following information, or have completed these steps:

 1. *Cloudflare credentials:*
 
	 a. Know your Cloudflare account username (or [register for an account if you're new to Cloudflare](https://dash.cloudflare.com/sign-up)); and
	 
	 b. Have your [API key](https://dash.cloudflare.com/profile/api-tokens) - no need to use your Global API key! (More info: [API keys](https://support.cloudflare.com/hc/en-us/articles/200167836-Managing-API-Tokens-and-Keys)).

	![image](https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/blob/master/docs/example4.png)


	 c. Create a API key with following (3) permissions:
	 
	 **Zone** > **Zone.Settings** > **Read**  
	 **Zone** > **Zone** > **Read**  
	 **Zone** > **DNS** > **Edit**  

	 The affected zone ressouces have to be (at least):

	**Include** > **All zones from an account** > `<domain>`  

 2. *DNS settings:*
 
	Ensure the DNS A record(s) for the domain/zone(s) you wish to update with this script have been created (More information: [Managing DNS records](https://support.cloudflare.com/hc/en-us/articles/360019093151-Managing-DNS-records-in-Cloudflare)).

	Your DNS records should appear (or already be setup as follows) in Cloudflare:
	
	(Note: Having Proxied turned on for your A records isn't necessary, but it will prevent those snooping around from easily finding out your current IP address)

	![image](https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/blob/master/docs/example1.png)
	
3. *SSH access to your Synology device:*

If you haven't setup this access, see the following Synology Knowledge Base article:
[How can I sign in to DSM/SRM with root privilege via SSH?[(https://kb.synology.com/en-id/DSM/tutorial/How_to_login_to_DSM_with_root_permission_via_SSH_Telnet)

4. *SRM users: Knowledge of vi:*

vi is the only text editor available within the [Busybox](https://linux.die.net/man/1/busybox) environment available at the SSH command line on devices running SRM.

For assistance with vi commands, see:
[Basic vi commands](https://www.cs.colostate.edu/helpdocs/vi.html)


## How to install

1. **SSH with root privledges on your supported device:**

	 a. For DSM Users:
	 
	 Navigate to __Control Panel > Terminal & SNMP > Enable SSH service__
	 
	 b. For SRM users:
	 
	 Navigate to __Control Panel > Services > System Services > Terminal > Enable SSH service__
	 
	![image](https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/blob/master/docs/example2.png)

2. **Connect via SSH:** Connect to your supported device via SSH and execute command

* 🆕 For DSM Users
  ```
  wget https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/install.sh -O install.sh && sudo bash install.sh
  ```

* 🆕 For SRM Users
  Note: Ensure you are connected as root in your SSH session
  ```
  wget https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/install.sh -O install.sh && sudo bash install.sh
  ```

	**Note:** For SRM users, you must connect to your device as root. No other username will allow these commands to run.

3. **Update your DDNS settings:** 

	 a. *For DSM Users:* Navigate to __Control Panel > External Access > DDNS__ then add new DDNS
	 
	 b. *For SRM users:* Navigate to __Network Centre > Internet > QuickConnect & DDNS > DDNS__ and press the Add button:

	Add/Update the DDNS settings screen as follows:

	* Service provider: Select Cloudflare
    * 🆕Hostname: this field is not used anymore, you can put any value here
	* Username:
For a single domain: __mydomain.com__
For multiple domains: __subdomain.mydomain.com|vpn.mydomain.com__
	  🆕(ensure each domain is separated: `|`)🆕
    
        __Note: there is 256 symbols limit on Hostname input__
	* Password: Your created Cloudflare API Key

	![image](https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/blob/master/docs/example3.png)

	Finally, press the test connection button to confirm all information is correctly entered, before pressing Ok to save and confirm your details.

4. Enjoy 🍺 and __don't forget to deactivate SSH (step 1) if you don't need it__.

## Troubleshooting and known issues

### CloudFlare API free domains limitation

CloudFlare API doesn't support domains with a .cf, .ga, .gq, .ml, or .tk TLD (top-level domain)

For more details read here: https://github.com/mrikirill/SynologyDDNSCloudflareMultidomain/issues/28 and https://community.cloudflare.com/t/unable-to-update-ddns-using-api-for-some-tlds/167228/61

Response example:

```
{
  "result": null,
  "success": false,
  "errors": [
    {
      "code": 1038,
      "message": "You cannot use this API for domains with a .cf, .ga, .gq, .ml, or .tk TLD (top-level domain). To configure the DNS settings for this domain, use the Cloudflare Dashboard."
    }
  ],
  "messages": []
}
```

### Connection test failed or error returned

This will manifest as either 1020 error; or the update attempt not showing in your Cloudflare Audit logs.

That generally means you may not have entered something correctly in the DDNS screen for your domain(s).

Revisit [Before you begin](#before-you-begin) to ensure you have all the right information, then go back to Step 4 in [How to install](#how-to-install) to make sure everything is correctly entered.

**Handy hint:** You can also check your Cloudflare Audit logs to see what - if anything - has made it there with your API key (More information: [Understanding Cloudflare Audit Logs](https://support.cloudflare.com/hc/en-us/articles/115002833612-Understanding-Cloudflare-Audit-Logs)). Updates using the API will appear in the Audit logs as a Rec Set action.

### Cloudflare no longer listed as a DDNS provider after DSM or SRM updates

After system updates to either Synology DSM or SRM, you may find that:
* __/usr/syno/bin/ddns/cloudflare.php__ has been deleted;
* __/etc.defaults/ddns_provider.conf__ was reset to its default settings (settings for Cloudflare no longer included); and
* The DDNS settings in your DDNS panel constantly show Cloudflare's status as loading.

If this occurs, simply [repeat the How to install steps](#how-to-install) shown above.

## Default Cloudflare ports
Source [Identifying network ports compatible with Cloudflare's proxy](https://support.cloudflare.com/hc/en-us/articles/200169156-Identifying-network-ports-compatible-with-Cloudflare-s-proxy)

| HTTP ports supported by Cloudflare | HTTPS ports supported by Cloudflare |
|------------------------------------|-------------------------------------|
| 80                                 | 443                                 |
| 8080                               | 2053                                | 
| 8880                               | 2083                                |
| 2052                               | 2087                                | 
| 2082                               | 2096                                |
| 2086                               | 8443                                | 
| 2095                               |                                     |

## Debug

You can run this script directly to see output logs

* SSH into your Synology system 

* Run this command: 

```
/usr/bin/php -d open_basedir=/usr/syno/bin/ddns -f /usr/syno/bin/ddns/cloudflare.php "<username>" "<password>" "<hostname>" "<ipv4>"
```

* Check output logs

## Credits

<small><i><a href='http://ecotrust-canada.github.io/markdown-toc/'>Table of contents generated with markdown-toc</a></i></small>  
<small><i><a href='https://www.youtube.com/watch?v=Nf7m3h11y-s'>DB Tech - creating API keys and using Cloudflare CNAME for single updates</a></i></small>
<small><i><a href='https://www.mkdocs.org'>MKDoc - generate documentation</a></i></small>
