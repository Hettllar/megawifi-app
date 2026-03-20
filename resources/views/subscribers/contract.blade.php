<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عقد اشتراك - {{ $subscriber->full_name ?? $subscriber->username }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: A4;
            margin: 1.5cm;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.8;
            color: #333;
            background: #f5f5f5;
            direction: rtl;
        }
        
        .contract-container {
            max-width: 21cm;
            margin: 20px auto;
            background: white;
            padding: 40px 50px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        @media print {
            body {
                background: white;
            }
            .contract-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px double #2563eb;
            padding-bottom: 20px;
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .company-name {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .contract-title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .contract-number {
            font-size: 14px;
            color: #888;
            margin-top: 5px;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 15px;
            padding: 8px 15px;
            background: #eff6ff;
            border-right: 4px solid #2563eb;
            border-radius: 0 8px 8px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            border-bottom: 1px dotted #ddd;
            padding-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            min-width: 120px;
        }
        
        .info-value {
            color: #1f2937;
            flex: 1;
        }
        
        .info-value.highlight {
            font-weight: bold;
            color: #2563eb;
        }
        
        .terms {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .terms-title {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .terms-list {
            list-style: none;
            counter-reset: item;
        }
        
        .terms-list li {
            counter-increment: item;
            margin-bottom: 12px;
            padding-right: 30px;
            position: relative;
        }
        
        .terms-list li::before {
            content: counter(item) ".";
            position: absolute;
            right: 0;
            font-weight: bold;
            color: #2563eb;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 50px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-title {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 60px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        
        .date-section {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #555;
        }
        
        .print-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
        }
        
        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #6b7280;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            background: #4b5563;
        }

        .service-box {
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #2563eb;
            margin-bottom: 20px;
        }

        .service-box .service-name {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            text-align: center;
        }

        .stamp-area {
            width: 100px;
            height: 100px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="contract-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">📡 MegaWiFi</div>
            <div class="company-name">{{ $subscriber->router->name }}</div>
            <div class="contract-title">عقد اشتراك خدمة الإنترنت</div>
            <div class="contract-number">رقم العقد: #{{ str_pad($subscriber->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>

        <!-- Service Info -->
        <div class="service-box">
            <div class="service-name">
                🚀 باقة: {{ $subscriber->profile ?? 'غير محدد' }}
            </div>
        </div>

        <!-- Customer Info -->
        <div class="section">
            <div class="section-title">📋 بيانات المشترك (الطرف الثاني)</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">الاسم الكامل:</span>
                    <span class="info-value highlight">{{ $subscriber->full_name ?? '_______________' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">اسم المستخدم:</span>
                    <span class="info-value">{{ $subscriber->username }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">الرقم الوطني:</span>
                    <span class="info-value">{{ $subscriber->national_id ?? '_______________' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">رقم الهاتف:</span>
                    <span class="info-value">{{ $subscriber->phone ?? '_______________' }}</span>
                </div>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">العنوان:</span>
                    <span class="info-value">{{ $subscriber->address ?? '_______________________________________________' }}</span>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="section">
            <div class="section-title">🔐 بيانات الحساب</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">اسم المستخدم:</span>
                    <span class="info-value highlight">{{ $subscriber->username }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">كلمة المرور:</span>
                    <span class="info-value highlight">{{ $subscriber->password }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">نوع الخدمة:</span>
                    <span class="info-value">{{ $subscriber->type === 'ppp' ? 'PPPoE (برودباند)' : ($subscriber->type === 'hotspot' ? 'هوتسبوت' : 'يوزرمانجر') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">تاريخ الاشتراك:</span>
                    <span class="info-value">{{ $subscriber->created_at->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="section">
            <div class="section-title">📜 شروط وأحكام الخدمة</div>
            <div class="terms">
                <ol class="terms-list">
                    <li>يلتزم المشترك بدفع رسوم الاشتراك الشهرية في موعدها المحدد، وعند التأخر عن السداد يحق للشركة تعليق الخدمة.</li>
                    <li>الخدمة شخصية وغير قابلة للتحويل للغير، ولا يجوز مشاركتها مع أي طرف آخر دون إذن مسبق.</li>
                    <li>يتحمل المشترك مسؤولية الحفاظ على سرية بيانات حسابه (اسم المستخدم وكلمة المرور).</li>
                    <li>يلتزم المشترك بعدم استخدام الخدمة في أي أنشطة مخالفة للقانون أو الآداب العامة.</li>
                    <li>يحق للشركة تعديل سرعة الخدمة أو شروطها مع إخطار المشترك مسبقاً.</li>
                    <li>في حالة وجود أي خلل فني، يجب على المشترك إبلاغ الشركة فوراً للصيانة.</li>
                    <li>عند الرغبة في إلغاء الاشتراك، يجب إخطار الشركة قبل نهاية الشهر بأسبوع على الأقل.</li>
                    <li>يقر المشترك بأنه قد قرأ وفهم جميع الشروط والأحكام الواردة في هذا العقد ويوافق عليها.</li>
                </ol>
            </div>
        </div>

        <!-- Date -->
        <div class="date-section">
            <strong>تاريخ التعاقد:</strong> {{ now()->format('Y-m-d') }} م
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>مكان التعاقد:</strong> {{ $subscriber->router->name }}
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">الطرف الأول (مقدم الخدمة)</div>
                <div class="stamp-area">الختم</div>
                <div class="signature-line" style="margin-top: 20px;">التوقيع والختم</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">الطرف الثاني (المشترك)</div>
                <div style="height: 100px;"></div>
                <div class="signature-line">
                    {{ $subscriber->full_name ?? '________________' }}
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>هذا العقد صادر من نظام MegaWiFi لإدارة شبكات الإنترنت</p>
            <p>تم إنشاء هذا العقد بتاريخ {{ now()->format('Y-m-d H:i') }}</p>
        </div>
    </div>

    <!-- Print & Back Buttons -->
    <button class="print-btn no-print" onclick="window.print()">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        طباعة العقد
    </button>

    <a href="{{ route('subscribers.edit', $subscriber) }}" class="back-btn no-print">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        رجوع
    </a>
</body>
</html>
