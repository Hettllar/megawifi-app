<?php
// اتصال بقاعدة البيانات
\ = new mysqli('localhost', 'root', 'Maher@1234', 'megawifi');

if (\->connect_error) {
    die('خطأ في الاتصال بقاعدة البيانات');
}

\->set_charset('utf8mb4');

\ = \['user_id'] ?? null;

if (!\) {
    die('يرجى إدخال معرف المستخدم: ?user_id=YOUR_ID');
}

// Get user and IPTV subscription
\ = " SELECT u.name i.username i.password i.expires_at i.max_connections i.is_active
