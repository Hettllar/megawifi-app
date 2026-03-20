#!/bin/bash
# استخدم هذا السكريبت عند الحصول على روابط beIN Sports

echo 'قم بتعديل الروابط أدناه ثم نفّذ السكريبت'
echo ''
echo 'mysql megawifi << SQL_COMMANDS'
echo " UPDATE iptv_channels SET source_url = YOUR_BEIN1_URL_HERE is_active = 1 WHERE slug = bein-sports-1-hd
