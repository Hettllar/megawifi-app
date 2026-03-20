#!/bin/bash
# WireGuard Health Monitor for new server - runs every 5 minutes via cron
LOG="/opt/megawifi/storage/logs/wg-health.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
INTERFACE="wg0"

log() { echo "[$TIMESTAMP] $1" >> "$LOG"; }

# 1. Check if wg0 interface is UP
if ! ip link show $INTERFACE &>/dev/null; then
    log "CRITICAL: $INTERFACE is DOWN! Attempting restart..."
    systemctl restart wg-quick@$INTERFACE
    sleep 3
    if ip link show $INTERFACE &>/dev/null; then
        log "RECOVERY: $INTERFACE restarted successfully"
    else
        log "FAILED: Could not restart $INTERFACE"
    fi
    exit 1
fi

# 2. Check peer handshakes
TOTAL=0; ACTIVE=0; STALE=0; DEAD=0; NOW=$(date +%s)
while IFS=$'\t' read -r pubkey psk endpoint allowed_ips last_handshake rx tx keepalive; do
    [[ "$pubkey" == "$(wg show $INTERFACE public-key)" ]] && continue
    [[ -z "$allowed_ips" || "$allowed_ips" == "(none)" ]] && continue
    TOTAL=$((TOTAL + 1))
    if [[ "$last_handshake" == "0" ]]; then
        DEAD=$((DEAD + 1))
    else
        AGE=$((NOW - last_handshake))
        if [[ $AGE -lt 180 ]]; then ACTIVE=$((ACTIVE + 1))
        elif [[ $AGE -lt 600 ]]; then STALE=$((STALE + 1))
        else DEAD=$((DEAD + 1)); fi
    fi
done < <(wg show $INTERFACE dump | tail -n +2)

# 3. Check config vs live peers
LIVE_PEERS=$(wg show $INTERFACE peers | wc -l)
CONF_PEERS=$(grep -c '^\[Peer\]' /etc/wireguard/$INTERFACE.conf)
if [[ "$LIVE_PEERS" != "$CONF_PEERS" ]]; then
    log "WARNING: Config mismatch (live=$LIVE_PEERS, config=$CONF_PEERS). Saving..."
    wg-quick save $INTERFACE 2>/dev/null
fi

# 4. Check iptables FORWARD for wg0
if ! iptables -C FORWARD -i $INTERFACE -j ACCEPT 2>/dev/null; then
    log "WARNING: FORWARD rule for $INTERFACE missing! Re-adding..."
    iptables -A FORWARD -i $INTERFACE -j ACCEPT
fi

# 5. Check MASQUERADE
if ! iptables -t nat -C POSTROUTING -o eth0 -j MASQUERADE 2>/dev/null; then
    log "WARNING: MASQUERADE rule missing! Re-adding..."
    iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
fi

# 6. Check IP forwarding
if [[ $(cat /proc/sys/net/ipv4/ip_forward) != "1" ]]; then
    log "CRITICAL: IP forwarding disabled! Enabling..."
    echo 1 > /proc/sys/net/ipv4/ip_forward
fi

# 7. Log status periodically
MINUTE=$(date +%M)
if [[ $STALE -gt 0 || $DEAD -gt 2 || "$MINUTE" == "00" || "$MINUTE" == "30" ]]; then
    log "STATUS: Total=$TOTAL Active=$ACTIVE Stale=$STALE Dead=$DEAD"
fi

# 8. Rotate log if >5MB
if [[ -f "$LOG" ]] && [[ $(stat -c%s "$LOG" 2>/dev/null) -gt 5242880 ]]; then
    tail -1000 "$LOG" > "${LOG}.tmp"
    mv "${LOG}.tmp" "$LOG"
    log "Log rotated"
fi
