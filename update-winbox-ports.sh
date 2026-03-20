#!/bin/bash

echo "=== Updating WinBox Port Forwarding (Nginx Stream) ==="

# Get all routers from database using PHP
cd /var/www/megawifi
ROUTERS=$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$routers = App\Models\Router::where('is_active', true)->orderBy('public_port')->get(['id','name','wg_client_ip','public_port']);
foreach(\$routers as \$r) {
    if(\$r->wg_client_ip && \$r->public_port) {
        echo \$r->id . chr(9) . \$r->name . chr(9) . \$r->wg_client_ip . chr(9) . \$r->public_port . chr(10);
    }
}
")

# Create Nginx stream config
CONFIG_FILE="/etc/nginx/stream.d/winbox.conf"
echo "# Auto-generated WinBox port forwarding" > $CONFIG_FILE
echo "# Generated at: $(date)" >> $CONFIG_FILE
echo "" >> $CONFIG_FILE

# Counter
COUNT=0

# Process each router
while IFS=$'\t' read -r id name ip public_port; do
    if [ -z "$id" ]; then continue; fi
    echo "Router $id ($name): Port $public_port -> $ip:8291"
    
    cat >> $CONFIG_FILE << EOF
# Router: $name (ID: $id)
upstream winbox_$public_port {
    server $ip:8291;
}

server {
    listen $public_port;
    proxy_pass winbox_$public_port;
    proxy_timeout 3600s;
    proxy_connect_timeout 60s;
}

EOF
    COUNT=$((COUNT + 1))
done <<< "$ROUTERS"

echo ""
echo "=== Testing Nginx Configuration ==="
if nginx -t; then
    echo "Configuration OK, reloading Nginx..."
    systemctl reload nginx
    echo ""
    echo "=== Done! Configured $COUNT routers ==="
else
    echo "ERROR: Nginx configuration test failed!"
    exit 1
fi
