<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة بطاقات الهوتسبوت - {{ $router->name }}</title>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #581c87 50%, #1e1b4b 100%);
            min-height: 100vh;
            padding: 20px;
        }

        /* Screen-only styles */
        @media screen {
            .print-controls {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: rgba(0, 0, 0, 0.9);
                backdrop-filter: blur(10px);
                padding: 15px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                z-index: 1000;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                flex-wrap: wrap;
                gap: 10px;
            }

            .print-controls .info {
                color: white;
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .print-controls .info span {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .print-controls .info .number {
                background: linear-gradient(135deg, #8b5cf6, #ec4899);
                padding: 4px 12px;
                border-radius: 20px;
                font-weight: bold;
            }

            .print-controls .buttons {
                display: flex;
                gap: 15px;
            }

            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }

            .btn-print {
                background: linear-gradient(135deg, #8b5cf6, #ec4899);
                color: white;
            }

            .btn-print:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
            }

            .btn-share {
                background: linear-gradient(135deg, #10b981, #06b6d4);
                color: white;
            }

            .btn-share:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            }

            .btn-share:disabled {
                opacity: 0.7;
                cursor: wait;
            }

            .btn-back {
                background: rgba(255, 255, 255, 0.1);
                color: white;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .btn-back:hover {
                background: rgba(255, 255, 255, 0.2);
            }

            .cards-container {
                margin-top: 80px;
                padding: 20px;
            }

            .status-message {
                text-align: center;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 15px;
                color: white;
            }

            .status-success {
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(6, 182, 212, 0.2));
                border: 1px solid rgba(16, 185, 129, 0.5);
            }

            .status-warning {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(239, 68, 68, 0.2));
                border: 1px solid rgba(245, 158, 11, 0.5);
            }

            /* Screen preview - matches 36mm x 20mm ratio (horizontal card) */
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(144px, 1fr));
                gap: 6px;
                justify-items: center;
            }

            .card {
                width: 144px;
                height: 80px;
                border-radius: 6px;
                padding: 5px;
                position: relative;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            }
        }

        /* Card Design */
        .card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .card-content {
            position: relative;
            z-index: 1;
            height: 100%;
            width: 100%;
        }

        .card-element {
            position: absolute;
            transform: translateX(-50%);
            white-space: nowrap;
            text-align: center;
        }

        .card-network {
            font-weight: bold;
            line-height: 1.1;
        }

        .card-data-limit {
            font-weight: bold;
            line-height: 1.1;
        }

        .card-credentials {
            text-align: center;
        }

        .credential-label {
            font-size: 5px;
            opacity: 0.7;
            display: block;
            line-height: 1;
        }

        .credential-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .card-phone {
            opacity: 0.9;
        }

        /* Print Styles - 50 cards per A4 (10 cols x 5 rows) */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            html, body {
                width: 297mm !important;
                height: 210mm !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-controls,
            .status-message {
                display: none !important;
            }

            .cards-container {
                margin: 0 !important;
                padding: 2mm !important;
                width: 100% !important;
            }

            .cards-grid {
                display: grid !important;
                grid-template-columns: repeat(8, 36mm) !important;
                grid-template-rows: repeat(10, 20mm) !important;
                gap: 1mm !important;
                justify-content: center !important;
            }

            .card {
                width: 36mm !important;
                height: 20mm !important;
                margin: 0 !important;
                padding: 1mm !important;
                border-radius: 1.5mm !important;
                box-shadow: none !important;
                border: 0.2mm solid #666 !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }

            .card::before {
                display: none !important;
            }

            .card-content {
                height: 100% !important;
            }

            .card-header {
                margin-bottom: 1mm !important;
            }

            .card-title {
                font-size: 6pt !important;
                font-weight: bold !important;
            }

            .card-profile {
                font-size: 5pt !important;
            }

            .card-credentials {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
            }

            .credential-row {
                margin-bottom: 1.5mm !important;
            }

            .credential-label {
                font-size: 5pt !important;
                margin-bottom: 0.5mm !important;
            }

            .credential-value {
                font-size: 7pt !important;
                font-weight: bold !important;
                letter-spacing: 0 !important;
            }

            .card-footer {
                font-size: 4pt !important;
                margin-top: auto !important;
            }

            @page {
                size: A4 landscape;
                margin: 3mm;
            }
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <div class="info">
            <span>
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path>
                </svg>
                {{ $router->name }}
            </span>
            <span>البطاقات: <span class="number">{{ count($cards) }}</span></span>
            <span>الصفحات: <span class="number">{{ ceil(count($cards) / 50) }}</span></span>
            @if($profile)
            <span>البروفايل: <span class="number">{{ $profile }}</span></span>
            @endif
        </div>
        <div class="buttons">
            <a href="{{ route('hotspot.cards') }}" class="btn btn-back">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                رجوع
            </a>
            <button onclick="generateAndShare()" class="btn btn-share" id="shareBtn">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                </svg>
                <span id="shareBtnText">تحميل صورة</span>
            </button>
            <button onclick="window.print()" class="btn btn-print">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                طباعة
            </button>
        </div>
    </div>

    <div class="cards-container">
        @if($addedToRouter)
            @if($failedCount > 0)
                <div class="status-message status-warning">
                    ⚠️ تم إضافة {{ $addedCount }} بطاقة، فشلت {{ $failedCount }}
                </div>
            @else
                <div class="status-message status-success">
                    ✅ تمت إضافة {{ $addedCount }} بطاقة للراوتر
                </div>
            @endif
        @else
            <div class="status-message status-warning">
                📋 للعرض فقط - لم تُضف للراوتر
            </div>
        @endif

        <div class="cards-grid">
            @foreach($cards as $index => $card)
                <div class="card" style="background: {{ $cardColor }};">
                    <div class="card-content">
                        {{-- Network Name --}}
                        @if($networkName)
                            <div class="card-element card-network" 
                                 style="color: {{ $networkColor ?? $textColor }}; font-size: {{ $fontNetwork ?? 8 }}px; left: {{ explode(',', $posNetwork ?? '50,5')[0] }}%; top: {{ explode(',', $posNetwork ?? '50,5')[1] ?? 5 }}%;">
                                {{ $networkName }}
                            </div>
                        @endif
                        
                        {{-- Data Limit --}}
                        @if($showDataLimit && isset($card['data_limit_gb']) && $card['data_limit_gb'])
                            <div class="card-element card-data-limit" 
                                 style="color: {{ $textColor }}; font-size: {{ $fontDataLimit ?? 10 }}px; left: {{ explode(',', $posDataLimit ?? '50,22')[0] }}%; top: {{ explode(',', $posDataLimit ?? '50,22')[1] ?? 22 }}%;">
                                {{ $card['data_limit_gb'] }}G
                            </div>
                        @endif
                        
                        {{-- Username --}}
                        <div class="card-element card-credentials" 
                             style="left: {{ explode(',', $posUsername ?? '30,42')[0] }}%; top: {{ explode(',', $posUsername ?? '30,42')[1] ?? 42 }}%;">
                            <div class="credential-value" style="color: {{ $textColor }}; font-size: {{ $fontUsername ?? 10 }}px;">{{ $card['username'] }}</div>
                        </div>
                        
                        {{-- Password --}}
                        <div class="card-element card-credentials" 
                             style="left: {{ explode(',', $posPassword ?? '70,42')[0] }}%; top: {{ explode(',', $posPassword ?? '70,42')[1] ?? 42 }}%;">
                            <div class="credential-value" style="color: #FFD700; font-size: {{ $fontPassword ?? 10 }}px;">{{ $card['password'] }}</div>
                        </div>
                        
                        {{-- Phone Number --}}
                        @if($phoneNumber)
                            <div class="card-element card-phone" 
                                 style="color: {{ $textColor }}; font-size: {{ $fontPhone ?? 6 }}px; left: {{ explode(',', $posPhone ?? '50,80')[0] }}%; top: {{ explode(',', $posPhone ?? '50,80')[1] ?? 80 }}%;">
                                {{ $phoneNumber }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Hidden A4 Canvas for Image Generation -->
    <div id="a4Container" style="position: absolute; left: -9999px; top: 0;">
    </div>

    <script>
        const cards = @json($cards);
        const cardColor = '{{ $cardColor }}';
        const textColor = '{{ $textColor }}';
        const profile = '{{ $profile ?? "" }}';
        const routerName = '{{ $router->name }}';
        const networkName = '{{ $networkName ?? "" }}';
        const phoneNumber = '{{ $phoneNumber ?? "" }}';
        const showDataLimit = {{ $showDataLimit ? 'true' : 'false' }};
        const networkColor = '{{ $networkColor ?? $textColor }}';
        const fontSizes = {
            network: {{ $fontNetwork ?? 8 }},
            dataLimit: {{ $fontDataLimit ?? 10 }},
            username: {{ $fontUsername ?? 10 }},
            password: {{ $fontPassword ?? 10 }},
            phone: {{ $fontPhone ?? 6 }},
            labels: {{ $fontLabels ?? 5 }}
        };
        
        // Element positions (percentage based)
        const positions = {
            network: { x: {{ explode(',', $posNetwork ?? '50,5')[0] }}, y: {{ explode(',', $posNetwork ?? '50,5')[1] ?? 5 }} },
            dataLimit: { x: {{ explode(',', $posDataLimit ?? '50,22')[0] }}, y: {{ explode(',', $posDataLimit ?? '50,22')[1] ?? 22 }} },
            username: { x: {{ explode(',', $posUsername ?? '30,42')[0] }}, y: {{ explode(',', $posUsername ?? '30,42')[1] ?? 42 }} },
            password: { x: {{ explode(',', $posPassword ?? '70,42')[0] }}, y: {{ explode(',', $posPassword ?? '70,42')[1] ?? 42 }} },
            phone: { x: {{ explode(',', $posPhone ?? '50,80')[0] }}, y: {{ explode(',', $posPhone ?? '50,80')[1] ?? 80 }} }
        };

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        async function generateAndShare() {
            const btn = document.getElementById('shareBtn');
            const btnText = document.getElementById('shareBtnText');
            
            btn.disabled = true;
            btnText.textContent = 'جاري الإنشاء...';

            try {
                // A4 portrait dimensions at 300 DPI (high quality)
                const a4Width = 2480;  // 210mm * 300/25.4
                const a4Height = 3508; // 297mm * 300/25.4
                const cardsPerPage = 75; // 5 columns x 15 rows
                const cols = 5;
                const rows = 15;
                const margin = 30;
                const gap = 12;
                
                // Horizontal card dimensions (wider than tall)
                const cardWidth = Math.floor((a4Width - margin * 2 - gap * (cols - 1)) / cols);
                const cardHeight = Math.floor((a4Height - margin * 2 - 50 - gap * (rows - 1)) / rows);

                const totalPages = Math.ceil(cards.length / cardsPerPage);
                const images = [];

                for (let page = 0; page < totalPages; page++) {
                    const canvas = document.createElement('canvas');
                    canvas.width = a4Width;
                    canvas.height = a4Height;
                    const ctx = canvas.getContext('2d');

                    // White background
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, a4Width, a4Height);

                    // Header
                    ctx.fillStyle = '#1e1b4b';
                    ctx.font = 'bold 36px Segoe UI';
                    ctx.textAlign = 'center';
                    ctx.fillText(`${routerName} - بطاقات WiFi`, a4Width / 2, 35);
                    
                    if (totalPages > 1) {
                        ctx.font = '20px Segoe UI';
                        ctx.fillStyle = '#666';
                        ctx.fillText(`صفحة ${page + 1} من ${totalPages}`, a4Width / 2, 58);
                    }

                    const startIdx = page * cardsPerPage;
                    const endIdx = Math.min(startIdx + cardsPerPage, cards.length);
                    const pageCards = cards.slice(startIdx, endIdx);

                    pageCards.forEach((card, idx) => {
                        const col = idx % cols;
                        const row = Math.floor(idx / cols);
                        const x = margin + col * (cardWidth + gap);
                        const y = margin + 45 + row * (cardHeight + gap);

                        // Save context for clipping
                        ctx.save();
                        
                        // Create clipping path for rounded rectangle
                        roundRectPath(ctx, x, y, cardWidth, cardHeight, 12);
                        ctx.clip();

                        // Card background
                        ctx.fillStyle = cardColor;
                        ctx.fillRect(x, y, cardWidth, cardHeight);

                        // Decorative circles (like preview)
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
                        // Top-right circle
                        ctx.beginPath();
                        ctx.arc(x + cardWidth + 15, y - 20, cardWidth * 0.35, 0, Math.PI * 2);
                        ctx.fill();
                        // Bottom-left circle
                        ctx.beginPath();
                        ctx.arc(x - 15, y + cardHeight + 18, cardWidth * 0.28, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.restore();

                        // Card border
                        ctx.strokeStyle = 'rgba(255,255,255,0.2)';
                        ctx.lineWidth = 1;
                        roundRect(ctx, x, y, cardWidth, cardHeight, 12, false, true);

                        ctx.fillStyle = textColor;
                        ctx.textAlign = 'center';

                        // Use custom positions (percentage to pixel conversion)
                        const getX = (percent) => x + (cardWidth * percent / 100);
                        const getY = (percent) => y + (cardHeight * percent / 100);
                        
                        // Network name (custom color)
                        if (networkName) {
                            ctx.fillStyle = networkColor;
                            ctx.font = `bold ${fontSizes.network * 3}px Segoe UI`;
                            ctx.fillText(networkName, getX(positions.network.x), getY(positions.network.y));
                            ctx.fillStyle = textColor; // Reset to text color
                        }
                        
                        // Data limit (like "10G")
                        if (showDataLimit && card.data_limit_gb) {
                            ctx.font = `bold ${fontSizes.dataLimit * 3}px Segoe UI`;
                            ctx.fillText(card.data_limit_gb + 'G', getX(positions.dataLimit.x), getY(positions.dataLimit.y));
                        }
                        
                        // Username (white color)
                        ctx.fillStyle = textColor;
                        ctx.font = `bold ${fontSizes.username * 4}px Courier New`;
                        ctx.fillText(card.username, getX(positions.username.x), getY(positions.username.y));

                        // Password (gold/yellow color for contrast)
                        ctx.fillStyle = '#FFD700';
                        ctx.font = `bold ${fontSizes.password * 4}px Courier New`;
                        ctx.fillText(card.password, getX(positions.password.x), getY(positions.password.y));
                        
                        // Reset color for phone
                        ctx.fillStyle = textColor;
                        
                        // Phone number
                        if (phoneNumber) {
                            ctx.font = `${fontSizes.phone * 3}px Segoe UI`;
                            ctx.globalAlpha = 0.8;
                            ctx.fillText(phoneNumber, getX(positions.phone.x), getY(positions.phone.y));
                            ctx.globalAlpha = 1;
                        }
                    });

                    images.push(canvas.toDataURL('image/png', 1.0));
                }

                // Download images
                if (images.length === 1) {
                    downloadImage(images[0], `wifi-cards-${routerName}.png`);
                } else {
                    for (let i = 0; i < images.length; i++) {
                        downloadImage(images[i], `wifi-cards-${routerName}-page${i + 1}.png`);
                        await new Promise(r => setTimeout(r, 500));
                    }
                }

                // Try to share if supported
                if (navigator.share && images.length === 1) {
                    try {
                        const response = await fetch(images[0]);
                        const blob = await response.blob();
                        const file = new File([blob], `wifi-cards-${routerName}.png`, { type: 'image/png' });
                        
                        await navigator.share({
                            files: [file],
                            title: 'بطاقات WiFi',
                            text: `بطاقات WiFi - ${routerName}`
                        });
                    } catch (e) {
                        console.log('Share cancelled or not supported');
                    }
                }

                btnText.textContent = 'تم التحميل ✓';
                setTimeout(() => {
                    btnText.textContent = 'تحميل صورة';
                }, 2000);

            } catch (error) {
                console.error('Error generating image:', error);
                btnText.textContent = 'حدث خطأ';
                setTimeout(() => {
                    btnText.textContent = 'تحميل صورة';
                }, 2000);
            }

            btn.disabled = false;
        }

        function roundRect(ctx, x, y, width, height, radius, fill, stroke) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
            if (fill) ctx.fill();
            if (stroke) ctx.stroke();
        }

        function roundRectPath(ctx, x, y, width, height, radius) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
        }

        function downloadImage(dataUrl, filename) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = dataUrl;
            link.click();
        }
    </script>
</body>
</html>
