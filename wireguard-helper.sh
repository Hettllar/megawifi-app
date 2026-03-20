#!/bin/bash
# wireguard-helper: watches /opt/megawifi/wg-cmd directory for command files
# The PHP app writes command files, this script executes them on the host
# Pattern identical to iptables-helper.sh

CMD_DIR=/opt/megawifi/wg-cmd
RESULT_DIR=/opt/megawifi/wg-result
LOG=/var/log/wireguard-helper.log
WG=/usr/bin/wg
WG_QUICK=/usr/bin/wg-quick

mkdir -p "$CMD_DIR" "$RESULT_DIR"
chmod 777 "$CMD_DIR" "$RESULT_DIR"

echo "$(date): wireguard-helper started" >> "$LOG"

inotifywait -m -e create "$CMD_DIR" 2>/dev/null | while read dir event file; do
    FILEPATH="$CMD_DIR/$file"
    [ ! -f "$FILEPATH" ] && continue

    # Read command
    CMD=$(cat "$FILEPATH" 2>/dev/null)
    rm -f "$FILEPATH"

    ACTION=$(echo "$CMD" | awk '{print $1}')
    RESULT_FILE="$RESULT_DIR/$file"

    case "$ACTION" in
        add-peer)
            # Format: add-peer <interface> <public_key> <allowed_ip>
            IFACE=$(echo "$CMD" | awk '{print $2}')
            PUBKEY=$(echo "$CMD" | awk '{print $3}')
            ALLOWED_IP=$(echo "$CMD" | awk '{print $4}')

            # Validate interface name (alphanumeric + dash, max 15 chars)
            if ! echo "$IFACE" | grep -qE '^[a-zA-Z0-9_-]{1,15}$'; then
                echo "ERROR: invalid interface name" > "$RESULT_FILE"
                echo "$(date): add-peer REJECTED invalid interface: $IFACE" >> "$LOG"
                continue
            fi

            # Validate public key (base64, 44 chars)
            if ! echo "$PUBKEY" | grep -qE '^[A-Za-z0-9+/]{42,44}={0,2}$'; then
                echo "ERROR: invalid public key" > "$RESULT_FILE"
                echo "$(date): add-peer REJECTED invalid key" >> "$LOG"
                continue
            fi

            # Validate allowed IP (must be 10.10.0.x/32)
            if ! echo "$ALLOWED_IP" | grep -qE '^10\.10\.0\.[0-9]+/32$'; then
                echo "ERROR: invalid allowed IP" > "$RESULT_FILE"
                echo "$(date): add-peer REJECTED invalid IP: $ALLOWED_IP" >> "$LOG"
                continue
            fi

            OUTPUT=$($WG set "$IFACE" peer "$PUBKEY" allowed-ips "$ALLOWED_IP" persistent-keepalive 25 2>&1)
            RET=$?

            if [ $RET -eq 0 ]; then
                # Save config for persistence
                $WG_QUICK save "$IFACE" 2>/dev/null
                echo "OK" > "$RESULT_FILE"
            else
                echo "ERROR: $OUTPUT" > "$RESULT_FILE"
            fi
            echo "$(date): add-peer $IFACE key=${PUBKEY:0:8}... ip=$ALLOWED_IP (ret=$RET)" >> "$LOG"
            ;;

        remove-peer)
            # Format: remove-peer <interface> <public_key>
            IFACE=$(echo "$CMD" | awk '{print $2}')
            PUBKEY=$(echo "$CMD" | awk '{print $3}')

            if ! echo "$IFACE" | grep -qE '^[a-zA-Z0-9_-]{1,15}$'; then
                echo "ERROR: invalid interface name" > "$RESULT_FILE"
                continue
            fi

            if ! echo "$PUBKEY" | grep -qE '^[A-Za-z0-9+/]{42,44}={0,2}$'; then
                echo "ERROR: invalid public key" > "$RESULT_FILE"
                continue
            fi

            OUTPUT=$($WG set "$IFACE" peer "$PUBKEY" remove 2>&1)
            RET=$?

            if [ $RET -eq 0 ]; then
                $WG_QUICK save "$IFACE" 2>/dev/null
                echo "OK" > "$RESULT_FILE"
            else
                echo "ERROR: $OUTPUT" > "$RESULT_FILE"
            fi
            echo "$(date): remove-peer $IFACE key=${PUBKEY:0:8}... (ret=$RET)" >> "$LOG"
            ;;

        show-peers)
            # Format: show-peers <interface>
            IFACE=$(echo "$CMD" | awk '{print $2}')

            if ! echo "$IFACE" | grep -qE '^[a-zA-Z0-9_-]{1,15}$'; then
                echo "ERROR: invalid interface name" > "$RESULT_FILE"
                continue
            fi

            OUTPUT=$($WG show "$IFACE" allowed-ips 2>&1)
            RET=$?

            if [ $RET -eq 0 ]; then
                echo "$OUTPUT" > "$RESULT_FILE"
            else
                echo "ERROR: $OUTPUT" > "$RESULT_FILE"
            fi
            ;;

        show-all)
            # Format: show-all <interface>
            IFACE=$(echo "$CMD" | awk '{print $2}')

            if ! echo "$IFACE" | grep -qE '^[a-zA-Z0-9_-]{1,15}$'; then
                echo "ERROR: invalid interface name" > "$RESULT_FILE"
                continue
            fi

            OUTPUT=$($WG show "$IFACE" 2>&1)
            RET=$?

            if [ $RET -eq 0 ]; then
                echo "$OUTPUT" > "$RESULT_FILE"
            else
                echo "ERROR: $OUTPUT" > "$RESULT_FILE"
            fi
            ;;

        *)
            echo "ERROR: unknown command" > "$RESULT_FILE"
            echo "$(date): unknown command: $ACTION" >> "$LOG"
            ;;
    esac
done
