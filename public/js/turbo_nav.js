// تسريع التنقل وتحميل الصفحات
(function() {
    'use strict';
    
    // حفظ الشريط السفلي في الذاكرة
    const bottomNav = document.querySelector('nav.fixed.bottom-0');
    if (bottomNav) {
        bottomNav.style.transform = 'translateZ(0)';
        bottomNav.style.willChange = 'auto';
    }
    
    // Prefetch الصفحات عند تمرير الماوس
    const links = document.querySelectorAll('a[href^="/"]');
    const prefetched = new Set();
    
    links.forEach(link => {
        link.addEventListener('mouseenter', function() {
            const href = this.getAttribute('href');
            if (!prefetched.has(href) && !href.includes('#')) {
                const prefetchLink = document.createElement('link');
                prefetchLink.rel = 'prefetch';
                prefetchLink.href = href;
                document.head.appendChild(prefetchLink);
                prefetched.add(href);
            }
        }, { once: true });
    });
    
    // إظهار مؤشر التحميل عند النقر
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href^="/"]');
        if (link && !link.hasAttribute('target') && !e.ctrlKey && !e.metaKey) {
            // إضافة تأثير التحميل
            document.body.style.opacity = '0.8';
            document.body.style.transition = 'opacity 0.1s';
        }
    });
    
    // تحسين أداء الصفحة
    if ('connection' in navigator) {
        // تعطيل animations على الشبكات البطيئة
        if (navigator.connection.saveData || navigator.connection.effectiveType === '2g') {
            document.documentElement.classList.add('reduce-motion');
        }
    }
})();
