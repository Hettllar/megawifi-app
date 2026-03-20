#!/bin/bash
# استيراد قنوات IPTV من a2f0g.rfcot.com تلقائياً عندما يعود للعمل

SERVER="http://a2f0g.rfcot.com"
USERNAME="TestAdm4card"
PASSWORD="adm4card"
DB_NAME="megawifi"
DB_USER="root"
DB_PASS="Maher@1234"

echo "[$(date)] محاولة استيراد القنوات من $SERVER..."

# جلب قائمة القنوات المباشرة
CHANNELS_JSON=$(timeout 15 curl -s "$SERVER/player_api.php?username=$USERNAME&password=$PASSWORD&action=get_live_streams")

# التحقق من نجاح الطلب
if [ -z "$CHANNELS_JSON" ] || echo "$CHANNELS_JSON" | grep -q "error code"; then
    echo "[$(date)] ❌ السيرفر لا يزال معطل - لم يتم الاستيراد"
    exit 1
fi

# حساب عدد القنوات
CHANNEL_COUNT=$(echo "$CHANNELS_JSON" | grep -o '"stream_id":' | wc -l)

if [ "$CHANNEL_COUNT" -eq 0 ]; then
    echo "[$(date)] ❌ لا توجد قنوات للاستيراد"
    exit 1
fi

echo "[$(date)] ✅ تم جلب $CHANNEL_COUNT قناة من السيرفر"

# حذف القنوات القديمة من beIN وSSC (جاهزة لإعادة الاستيراد)
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DELETE FROM iptv_channels WHERE slug LIKE 'bein-%' OR slug LIKE 'ssc-%';"

# معالجة JSON واستخراج القنوات
echo "$CHANNELS_JSON" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    if not isinstance(data, list):
        sys.exit(1)
    
    for channel in data[:50]:  # استيراد أول 50 قناة فقط
        name = channel.get('name', '').replace(\"'\", \"\\'\")
        stream_id = channel.get('stream_id', '')
        category = channel.get('category_id', 'general')
        
        if not name or not stream_id:
            continue
        
        # إنشاء slug
        slug = name.lower().replace(' ', '-').replace('(', '').replace(')', '')[:50]
        
        # رابط البث
        source_url = 'http://a2f0g.rfcot.com:80/$USERNAME/$PASSWORD/$stream_id'.replace('$USERNAME', '$USERNAME').replace('$PASSWORD', '$PASSWORD').replace('$stream_id', str(stream_id))
        
        # طباعة SQL INSERT
        print(f\"INSERT IGNORE INTO iptv_channels (name, slug, source_url, category, language, stream_format, is_active, sort_order) VALUES ('{name}', '{slug}', '{source_url}', 'sports', 'ar', 'ts', 1, 100);\")
except:
    pass
" > /tmp/import_channels.sql

# استيراد القنوات
if [ -s /tmp/import_channels.sql ]; then
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /tmp/import_channels.sql
    IMPORTED=$(wc -l < /tmp/import_channels.sql)
    echo "[$(date)] ✅ تم استيراد $IMPORTED قناة بنجاح!"
    
    # إرسال إشعار
    echo "تم استيراد $IMPORTED قناة IPTV من a2f0g.rfcot.com" >> /var/www/megawifi/storage/logs/iptv_import_success.log
else
    echo "[$(date)] ❌ فشل معالجة القنوات"
fi

# تنظيف
rm -f /tmp/import_channels.sql
