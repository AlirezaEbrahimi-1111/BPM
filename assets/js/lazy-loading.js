/**
 * سیستم Lazy Loading و بهینه‌سازی عملکرد Frontend
 * این فایل شامل کلاس‌ها و توابع برای بهینه‌سازی بارگذاری و عملکرد است
 */

// ========================================
// 1. Lazy Loading تصاویر
// ========================================

class LazyImageLoader {
    constructor(options = {}) {
        this.options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.01,
            ...options
        };
        
        this.observer = null;
        this.init();
    }
    
    init() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(
                this.handleIntersection.bind(this),
                this.options
            );
            
            this.observeImages();
        } else {
            // Fallback برای مرورگرهای قدیمی
            this.loadAllImages();
        }
    }
    
    observeImages() {
        const images = document.querySelectorAll('img[data-src], img[data-srcset]');
        images.forEach(img => this.observer.observe(img));
    }
    
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.loadImage(entry.target);
                this.observer.unobserve(entry.target);
            }
        });
    }
    
    loadImage(img) {
        const src = img.getAttribute('data-src');
        const srcset = img.getAttribute('data-srcset');
        
        if (src) {
            img.src = src;
            img.removeAttribute('data-src');
        }
        
        if (srcset) {
            img.srcset = srcset;
            img.removeAttribute('data-srcset');
        }
        
        img.classList.add('loaded');
    }
    
    loadAllImages() {
        const images = document.querySelectorAll('img[data-src], img[data-srcset]');
        images.forEach(img => this.loadImage(img));
    }
}

// ========================================
// 2. Infinite Scroll برای لیست‌ها
// ========================================

class InfiniteScroll {
    constructor(options = {}) {
        this.container = options.container;
        this.loadMore = options.loadMore;
        this.threshold = options.threshold || 200;
        this.isLoading = false;
        this.hasMore = true;
        this.page = 1;
        
        this.init();
    }
    
    init() {
        if (!this.container) return;
        
        window.addEventListener('scroll', this.handleScroll.bind(this));
    }
    
    handleScroll() {
        if (this.isLoading || !this.hasMore) return;
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight;
        const clientHeight = document.documentElement.clientHeight;
        
        if (scrollTop + clientHeight >= scrollHeight - this.threshold) {
            this.load();
        }
    }
    
    async load() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoader();
        
        try {
            const hasMore = await this.loadMore(this.page);
            this.hasMore = hasMore;
            this.page++;
        } catch (error) {
            console.error('Error loading more:', error);
        } finally {
            this.isLoading = false;
            this.hideLoader();
        }
    }
    
    showLoader() {
        const loader = document.getElementById('infinite-loader');
        if (loader) loader.style.display = 'block';
    }
    
    hideLoader() {
        const loader = document.getElementById('infinite-loader');
        if (loader) loader.style.display = 'none';
    }
    
    reset() {
        this.page = 1;
        this.hasMore = true;
        this.isLoading = false;
    }
}

// ========================================
// 3. Debounce و Throttle
// ========================================

function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit = 300) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ========================================
// 4. کش مدیریت
// ========================================

class CacheManager {
    constructor(prefix = 'app_cache_') {
        this.prefix = prefix;
        this.memoryCache = new Map();
    }
    
    set(key, value, ttl = 3600000) { // TTL به میلی‌ثانیه (پیش‌فرض 1 ساعت)
        const item = {
            value: value,
            expiry: Date.now() + ttl
        };
        
        // ذخیره در Memory
        this.memoryCache.set(key, item);
        
        // ذخیره در SessionStorage
        try {
            sessionStorage.setItem(
                this.prefix + key,
                JSON.stringify(item)
            );
        } catch (e) {
            console.warn('SessionStorage full:', e);
        }
    }
    
    get(key) {
        // ابتدا از Memory بخوان
        if (this.memoryCache.has(key)) {
            const item = this.memoryCache.get(key);
            if (Date.now() < item.expiry) {
                return item.value;
            }
            this.memoryCache.delete(key);
        }
        
        // سپس از SessionStorage
        try {
            const itemStr = sessionStorage.getItem(this.prefix + key);
            if (!itemStr) return null;
            
            const item = JSON.parse(itemStr);
            if (Date.now() < item.expiry) {
                this.memoryCache.set(key, item);
                return item.value;
            }
            
            sessionStorage.removeItem(this.prefix + key);
        } catch (e) {
            console.error('Cache read error:', e);
        }
        
        return null;
    }
    
    remove(key) {
        this.memoryCache.delete(key);
        sessionStorage.removeItem(this.prefix + key);
    }
    
    clear() {
        this.memoryCache.clear();
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(this.prefix)) {
                sessionStorage.removeItem(key);
            }
        });
    }
}

// ========================================
// 5. Request Queue با Priority
// ========================================

class RequestQueue {
    constructor(maxConcurrent = 3) {
        this.maxConcurrent = maxConcurrent;
        this.running = 0;
        this.queue = [];
    }
    
    async add(request, priority = 0) {
        return new Promise((resolve, reject) => {
            this.queue.push({
                request,
                priority,
                resolve,
                reject
            });
            
            this.queue.sort((a, b) => b.priority - a.priority);
            this.process();
        });
    }
    
    async process() {
        if (this.running >= this.maxConcurrent || this.queue.length === 0) {
            return;
        }
        
        this.running++;
        const item = this.queue.shift();
        
        try {
            const result = await item.request();
            item.resolve(result);
        } catch (error) {
            item.reject(error);
        } finally {
            this.running--;
            this.process();
        }
    }
}

// ========================================
// 6. بهینه‌سازی رندرینگ لیست بزرگ
// ========================================

class VirtualScroll {
    constructor(options) {
        this.container = options.container;
        this.items = options.items || [];
        this.itemHeight = options.itemHeight || 100;
        this.renderItem = options.renderItem;
        this.buffer = options.buffer || 3;
        
        this.visibleStart = 0;
        this.visibleEnd = 0;
        
        this.init();
    }
    
    init() {
        this.container.style.position = 'relative';
        this.container.style.overflow = 'auto';
        
        this.contentHeight = this.items.length * this.itemHeight;
        
        const spacer = document.createElement('div');
        spacer.style.height = this.contentHeight + 'px';
        this.container.appendChild(spacer);
        
        this.viewport = document.createElement('div');
        this.viewport.style.position = 'absolute';
        this.viewport.style.top = '0';
        this.viewport.style.left = '0';
        this.viewport.style.right = '0';
        this.container.appendChild(this.viewport);
        
        this.container.addEventListener('scroll', throttle(() => {
            this.render();
        }, 16)); // ~60fps
        
        this.render();
    }
    
    render() {
        const scrollTop = this.container.scrollTop;
        const viewportHeight = this.container.clientHeight;
        
        this.visibleStart = Math.floor(scrollTop / this.itemHeight);
        this.visibleEnd = Math.ceil((scrollTop + viewportHeight) / this.itemHeight);
        
        // اضافه کردن buffer
        const start = Math.max(0, this.visibleStart - this.buffer);
        const end = Math.min(this.items.length, this.visibleEnd + this.buffer);
        
        this.viewport.innerHTML = '';
        this.viewport.style.transform = `translateY(${start * this.itemHeight}px)`;
        
        for (let i = start; i < end; i++) {
            const itemElement = this.renderItem(this.items[i], i);
            itemElement.style.height = this.itemHeight + 'px';
            this.viewport.appendChild(itemElement);
        }
    }
    
    updateItems(items) {
        this.items = items;
        this.contentHeight = items.length * this.itemHeight;
        this.container.querySelector('div').style.height = this.contentHeight + 'px';
        this.render();
    }
}

// ========================================
// 7. Prefetch و Preload
// ========================================

class ResourcePreloader {
    static prefetchPage(url) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }
    
    static preloadImage(url) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = url;
        document.head.appendChild(link);
    }
    
    static preloadScript(url) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'script';
        link.href = url;
        document.head.appendChild(link);
    }
    
    static preconnect(domain) {
        const link = document.createElement('link');
        link.rel = 'preconnect';
        link.href = domain;
        document.head.appendChild(link);
    }
}

// ========================================
// 8. Performance Monitoring
// ========================================

class PerformanceMonitor {
    static measurePageLoad() {
        if (!window.performance) return null;
        
        const perfData = window.performance.timing;
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
        const connectTime = perfData.responseEnd - perfData.requestStart;
        const renderTime = perfData.domComplete - perfData.domLoading;
        
        return {
            pageLoadTime,
            connectTime,
            renderTime,
            domReady: perfData.domContentLoadedEventEnd - perfData.navigationStart
        };
    }
    
    static measureFunction(fn, label) {
        const start = performance.now();
        const result = fn();
        const end = performance.now();
        
        console.log(`${label}: ${(end - start).toFixed(2)}ms`);
        return result;
    }
    
    static logToServer(metrics) {
        // ارسال متریک‌ها به سرور برای تحلیل
        if (navigator.sendBeacon) {
            const data = JSON.stringify(metrics);
            navigator.sendBeacon('/api/performance/log', data);
        }
    }
}

// ========================================
// 9. استفاده در پروژه
// ========================================

// راه‌اندازی Lazy Loading
const lazyLoader = new LazyImageLoader();

// راه‌اندازی Cache
const cache = new CacheManager();

// راه‌اندازی Request Queue
const requestQueue = new RequestQueue(3);

// این کد باید در فایل service-worker.js قرار گیرد
const SERVICE_WORKER_CODE = `
const CACHE_NAME = 'todo-system-v1';
const urlsToCache = [
    '/',
    '/pages/dashboard.php',
    '/assets/css/custom.css',
    '/assets/js/app.js',
    '/assets/css/bootstrap.min.css',
    '../assets/js/cdn/bootstrap-icons.css'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Cache hit - return response
                if (response) {
                    return response;
                }
                
                return fetch(event.request).then(response => {
                    // Check if valid response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone response
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME)
                        .then(cache => {
                            cache.put(event.request, responseToCache);
                        });
                    
                    return response;
                });
            })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
`;

// ثبت Service Worker
function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registered:', registration);
            })
            .catch(error => {
                console.log('Service Worker registration failed:', error);
            });
    }
}

// ========================================
// 11. Network Status Handler
// ========================================

class NetworkStatusHandler {
    constructor() {
        this.isOnline = navigator.onLine;
        this.init();
    }
    
    init() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.handleOnline();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.handleOffline();
        });
    }
    
    handleOnline() {
        console.log('Connection restored');
        this.showNotification('اتصال اینترنت برقرار شد', 'success');
        
        // Sync pending requests
        this.syncPendingRequests();
    }
    
    handleOffline() {
        console.log('Connection lost');
        this.showNotification('اتصال اینترنت قطع شد. برخی ویژگی‌ها در دسترس نیستند', 'warning');
    }
    
    showNotification(message, type) {
        // نمایش نوتیفیکیشن به کاربر
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
        notification.textContent = message;
        notification.style.zIndex = '9999';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    async syncPendingRequests() {
        // همگام‌سازی درخواست‌های معلق
        const pendingRequests = this.getPendingRequests();
        
        for (const request of pendingRequests) {
            try {
                await fetch(request.url, request.options);
                this.removePendingRequest(request.id);
            } catch (error) {
                console.error('Failed to sync request:', error);
            }
        }
    }
    
    getPendingRequests() {
        const pending = sessionStorage.getItem('pending_requests');
        return pending ? JSON.parse(pending) : [];
    }
    
    addPendingRequest(id, url, options) {
        const pending = this.getPendingRequests();
        pending.push({ id, url, options, timestamp: Date.now() });
        sessionStorage.setItem('pending_requests', JSON.stringify(pending));
    }
    
    removePendingRequest(id) {
        const pending = this.getPendingRequests();
        const filtered = pending.filter(req => req.id !== id);
        sessionStorage.setItem('pending_requests', JSON.stringify(filtered));
    }
}

// ========================================
// 12. Image Optimization
// ========================================

class ImageOptimizer {
    static async compressImage(file, maxWidth = 1920, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob((blob) => {
                        resolve(new File([blob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        }));
                    }, 'image/jpeg', quality);
                };
                
                img.onerror = reject;
                img.src = e.target.result;
            };
            
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }
    
    static generateThumbnail(file, size = 150) {
        return this.compressImage(file, size, 0.7);
    }
}

// ========================================
// 13. Memory Leak Prevention
// ========================================

class MemoryManager {
    constructor() {
        this.intervals = new Set();
        this.timeouts = new Set();
        this.listeners = new Map();
    }
    
    setInterval(callback, delay) {
        const id = setInterval(callback, delay);
        this.intervals.add(id);
        return id;
    }
    
    setTimeout(callback, delay) {
        const id = setTimeout(() => {
            callback();
            this.timeouts.delete(id);
        }, delay);
        this.timeouts.add(id);
        return id;
    }
    
    addEventListener(element, event, handler) {
        if (!this.listeners.has(element)) {
            this.listeners.set(element, []);
        }
        
        this.listeners.get(element).push({ event, handler });
        element.addEventListener(event, handler);
    }
    
    cleanup() {
        // پاکسازی intervals
        this.intervals.forEach(id => clearInterval(id));
        this.intervals.clear();
        
        // پاکسازی timeouts
        this.timeouts.forEach(id => clearTimeout(id));
        this.timeouts.clear();
        
        // پاکسازی event listeners
        this.listeners.forEach((listeners, element) => {
            listeners.forEach(({ event, handler }) => {
                element.removeEventListener(event, handler);
            });
        });
        this.listeners.clear();
    }
}

// ========================================
// 14. فشرده‌سازی داده‌ها قبل از ارسال
// ========================================

class DataCompressor {
    static compress(data) {
        const jsonString = JSON.stringify(data);
        
        // استفاده از LZString برای فشرده‌سازی
        if (typeof LZString !== 'undefined') {
            return LZString.compress(jsonString);
        }
        
        return jsonString;
    }
    
    static decompress(compressed) {
        if (typeof LZString !== 'undefined') {
            return JSON.parse(LZString.decompress(compressed));
        }
        
        return JSON.parse(compressed);
    }
}

// ========================================
// 15. راه‌اندازی اتوماتیک
// ========================================

// راه‌اندازی خودکار هنگام بارگذاری صفحه
document.addEventListener('DOMContentLoaded', () => {
    // Lazy Loading
    new LazyImageLoader();
    
    // Network Status
    new NetworkStatusHandler();
    
    // Service Worker
    registerServiceWorker();
    
    // Performance Monitoring
    window.addEventListener('load', () => {
        const metrics = PerformanceMonitor.measurePageLoad();
        if (metrics) {
            console.log('Page Performance:', metrics);
            PerformanceMonitor.logToServer(metrics);
        }
    });
});

// ========================================
// Export کلاس‌ها برای استفاده
// ========================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        LazyImageLoader,
        InfiniteScroll,
        CacheManager,
        RequestQueue,
        VirtualScroll,
        ResourcePreloader,
        PerformanceMonitor,
        NetworkStatusHandler,
        ImageOptimizer,
        MemoryManager,
        DataCompressor,
        debounce,
        throttle
    };
}

// ========================================
// تنظیمات Global
// ========================================

// Cache Manager سراسری
window.appCache = new CacheManager();

// Request Queue سراسری
window.requestQueue = new RequestQueue(3);

// Memory Manager سراسری
window.memoryManager = new MemoryManager();

// پاکسازی خودکار هنگام خروج از صفحه
window.addEventListener('beforeunload', () => {
    window.memoryManager.cleanup();
});