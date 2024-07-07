#!/bin/bash

# Define constants
PHP_FILE_URL="https://raw.githubusercontent.com/mrikirill/SynologyDDNSCloudflareMultidomain/master/cloudflare.php"
PHP_FILE_DEST="/usr/syno/bin/ddns/cloudflare.php"
TEMP_FILE="/tmp/cloudflare.php"
DDNS_PROVIDER_CONF="/etc.defaults/ddns_provider.conf"
CLOUDFLARE_ENTRY="[Cloudflare]\n  modulepath=/usr/syno/bin/ddns/cloudflare.php\n  queryurl=https://www.cloudflare.com/\n"

print_message() {
    echo -e "\n[INFO] $1\n"
}

# Step 1: Download the PHP file to a temporary location
print_message "Downloading cloudflare.php..."
wget $PHP_FILE_URL -O $TEMP_FILE

# Step 2: Move the downloaded file to the destination
print_message "Copying cloudflare.php to $PHP_FILE_DEST..."
sudo cp $TEMP_FILE $PHP_FILE_DEST

# Step 3: Change permissions of the copied file
print_message "Changing permissions of cloudflare.php..."
sudo chmod 755 $PHP_FILE_DEST

# Step 4: Insert Cloudflare configuration into ddns_provider.conf
print_message "Adding Cloudflare configuration to ddns_provider.conf..."
if grep -q "\[Cloudflare\]" $DDNS_PROVIDER_CONF; then
    print_message "Cloudflare configuration already exists in ddns_provider.conf. Skipping..."
else
    sudo bash -c "echo -e \"$CLOUDFLARE_ENTRY\" >> $DDNS_PROVIDER_CONF"
    print_message "Cloudflare configuration added successfully."
fi

# Clean up temporary file
rm $TEMP_FILE

# Step 5: Delete the script itself
print_message "Deleting the installation script..."
rm -- "$0"

print_message "Installation completed."