#!/bin/bash
# iptables-helper: watches /opt/megawifi/iptables-cmd directory for command files
# The PHP app writes command files, this script executes them on the host

CMD_DIR=/opt/megawifi/iptables-cmd
RESULT_DIR=/opt/megawifi/iptables-result
LOG=/var/log/iptables-helper.log

mkdir -p "$CMD_DIR" "$RESULT_DIR"
chmod 777 "$CMD_DIR" "$RESULT_DIR"

echo "$(date): iptables-helper started" >> "$LOG"

inotifywait -m -e create "$CMD_DIR" 2>/dev/null | while read dir event file; do
    FILEPATH="$CMD_DIR/$file"
    [ ! -f "$FILEPATH" ] && continue

    # Read command
    CMD=$(cat "$FILEPATH" 2>/dev/null)
    rm -f "$FILEPATH"

    ACTION=$(echo "$CMD" | awk '{print $1}')
    PORT=$(echo "$CMD" | awk '{print $2}')
    IP=$(echo "$CMD" | awk '{print $3}')
    RESULT_FILE="$RESULT_DIR/$file"

    # Validate port
    if [ "$ACTION" != "save" ]; then
        if ! echo "$PORT" | grep -qE '^[0-9]+$' || [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
            echo "ERROR: invalid port" > "$RESULT_FILE"
            continue
        fi
    fi

    # Validate IP (must be WG subnet 10.10.0.x)
    if [ -n "$IP" ]; then
        if ! echo "$IP" | grep -qE '^10\.10\.0\.[0-9]+$'; then
            echo "ERROR: invalid IP" > "$RESULT_FILE"
            continue
        fi
    fi

    case "$ACTION" in
        add)
            # Remove any existing DNAT mapping for this external port (even if IP changed)
            while true; do
                LINE=$(iptables -t nat -L PREROUTING -n --line-numbers \
                    | awk -v p="$PORT" '$1 ~ /^[0-9]+$/ && $0 ~ ("dpt:" p) {print $1; exit}')
                [ -z "$LINE" ] && break
                iptables -t nat -D PREROUTING "$LINE" 2>/dev/null || break
            done

            # Remove duplicate per-IP rules before re-adding
            while iptables -t nat -D POSTROUTING -p tcp -d "$IP" --dport 8291 -j MASQUERADE 2>/dev/null; do :; done
            while iptables -D FORWARD -p tcp -d "$IP" --dport 8291 -j ACCEPT 2>/dev/null; do :; done

            iptables -t nat -A PREROUTING -p tcp --dport "$PORT" -j DNAT --to-destination "$IP":8291 2>&1
            RET=$?
            iptables -t nat -A POSTROUTING -p tcp -d "$IP" --dport 8291 -j MASQUERADE 2>&1
            iptables -C FORWARD -p tcp -d "$IP" --dport 8291 -j ACCEPT 2>/dev/null || \
                iptables -A FORWARD -p tcp -d "$IP" --dport 8291 -j ACCEPT 2>&1
            iptables-save > /etc/iptables/rules.v4 2>/dev/null
            if [ $RET -eq 0 ]; then
                echo "OK" > "$RESULT_FILE"
            else
                echo "ERROR: iptables failed" > "$RESULT_FILE"
            fi
            echo "$(date): add $PORT -> $IP:8291 (ret=$RET)" >> "$LOG"
            ;;
        remove)
            while iptables -t nat -D PREROUTING -p tcp --dport "$PORT" -j DNAT --to-destination "$IP":8291 2>/dev/null; do :; done
            while iptables -t nat -D POSTROUTING -p tcp -d "$IP" --dport 8291 -j MASQUERADE 2>/dev/null; do :; done
            while iptables -D FORWARD -p tcp -d "$IP" --dport 8291 -j ACCEPT 2>/dev/null; do :; done
            iptables-save > /etc/iptables/rules.v4 2>/dev/null
            echo "OK" > "$RESULT_FILE"
            echo "$(date): remove $PORT -> $IP:8291" >> "$LOG"
            ;;
        check)
            if iptables -t nat -L PREROUTING -n 2>/dev/null | grep -q "dpt:$PORT"; then
                echo "EXISTS" > "$RESULT_FILE"
            else
                echo "NOT_FOUND" > "$RESULT_FILE"
            fi
            ;;
        save)
            iptables-save > /etc/iptables/rules.v4 2>/dev/null
            echo "OK" > "$RESULT_FILE"
            ;;
        *)
            echo "ERROR: unknown" > "$RESULT_FILE"
            ;;
    esac
done
