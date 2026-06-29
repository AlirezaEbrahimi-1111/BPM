// تنظیمات پروژه
if (typeof APP_CONFIG === 'undefined') {
    const APP_CONFIG = {
        BASE_PATH: '/',
        API_PATH: '/api',
        PAGES_PATH: '/pages'
    };

    // تابع کمکی برای ساخت URL
    function getApiUrl(endpoint) {
        return APP_CONFIG.API_PATH + endpoint;
    }

    function getPageUrl(page) {
        return APP_CONFIG.PAGES_PATH + '/' + page;
    }

    console.log('Config loaded:', APP_CONFIG.BASE_PATH);
}