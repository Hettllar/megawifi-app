#!/bin/bash

SERVER='http://a2f0g.rfcot.com:80'
USERNAME='TestAdm4card'
PASSWORD='adm4card'

echo "[$(date)] بدء استيراد قنوات beIN Sports..."

# جلب قنوات beIN HD (category_id=50)
curl -s "$SERVER/player_api.php?username=$USERNAME&password=$PASSWORD&action=get_live_streams&category_id=50" > /tmp/bein_channels.json

# التحقق من نجاح الطلب
if [ ! -s /tmp/bein_channels.json ]; then
    echo "❌ فشلجلب القنوات"
    exit 1
fi

# معالجة JSON وإنشاء SQL
python3 << 'PYTHON_EOF' > /tmp/import_bein.sql
import json

try:
    with open('/tmp/bein_channels.json', 'r') as f:
        channels = json.load(f)
    
    print("USE megawifi;")
    print("DELETE FROM iptv_channels WHERE slug LIKE 'bein-sports-%';")
    
    for ch in channels[:10]:  # أول 10 قنوات beIN HD
        name = ch.get('name', '').replace("'", "\\'")
        stream_id = ch.get('stream_id', '')
        icon = ch.get('stream_icon', '').replace("'", "\\'")
        num = ch.get('num', 1)
        
        slug = f"bein-sports-{num}-hd"
        source_url = f"http://a2f0g.rfcot.com:80/TestAdm4card/adm4card/{stream_id}"
        
        sql = f"""INSERT INTO iptv_channels (name, slug, source_url, logo, category, language, stream_format, is_active, sort_order, description) 
VALUES ('{name}', '{slug}', '{source_url}', '{icon}', 'sports', 'ar', 'ts', 1, {100 + num}, 'beIN Sports HD');"""
        
        print(sql)

except Exception as e:
    print(f"-- خطأ: {e}")
PYTHON_EOF

# تنفيذ SQL
if [ -s /tmp/import_bein.sql ]; then
    mysql -uroot -pMaher@1234 megawifi < /tmp/import_bein.sql
    echo "✅ تم استيراد قنوات beIN Sports"
    mysql -uroot -pMaher@1234 megawifi -e 'SELECT name, is_active FROM iptv_channels WHERE slug LIKE "bein-sports-%" ORDER BY sort_order;'
else
    echo "❌ فشل إنشاء SQL"
fi

# تنظيف
rm -f /tmp/bein_channels.json /tmp/import_bein.sql
