// فایل assets/js/persian-date-utils.js
// توابع کمکی برای تبدیل و نمایش تاریخ شمسی

/**
 * تبدیل تاریخ میلادی به شمسی
 * @param {Date|string} date - تاریخ میلادی
 * @returns {string} - تاریخ شمسی به فرمت YYYY/MM/DD
 */
function convertToJalali(date) {
    if (!date) return '';
    
    const d = new Date(date);
    if (isNaN(d.getTime())) return '';
    
    return d.toLocaleDateString('fa-IR');
}

/**
 * تبدیل تاریخ شمسی به میلادی
 * @param {string} jalaliDate - تاریخ شمسی به فرمت YYYY/MM/DD
 * @returns {string} - تاریخ میلادی به فرمت YYYY-MM-DD
 */
function convertToGregorian(jalaliDate) {
    if (!jalaliDate || jalaliDate.length < 8) return '';
    
    try {
        // پاک کردن کاراکترهای اضافی
        const cleanDate = jalaliDate.replace(/[^\d/]/g, '');
        const parts = cleanDate.split('/');
        
        if (parts.length !== 3) return '';
        
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        
        // اعتبارسنجی اولیه
        if (year < 1300 || year > 1500 || month < 1 || month > 12 || day < 1 || day > 31) {
            return '';
        }
        
        // تبدیل شمسی به میلادی با استفاده از Intl.DateTimeFormat
        const gregorianDate = jalaliToGregorian(year, month, day);
        return gregorianDate;
        
    } catch (error) {
        console.error('Error converting date:', error);
        return '';
    }
}

/**
 * تبدیل تاریخ شمسی به میلادی → 'YYYY-MM-DD'
 * ⚠️ نسخهٔ قبلی خراب بود (روز ژولیانی را میلی‌ثانیه حساب می‌کرد: ۱۴۰۵/۰۷/۲۹ → «7701-06-06»).
 *    این همان الگوریتم مرجع jdf است (هم‌خانوادهٔ TimeSync.gregorianToJalali در time-sync.js)
 *    و فقط عدد برمی‌گرداند — به ساعت/منطقهٔ زمانی دستگاه وابسته نیست (۱۴۰۵/۰۷/۳۰).
 */
function jalaliToGregorian(jy, jm, jd) {
    jy = +jy; jm = +jm; jd = +jd;
    var gy = (jy > 979) ? 1600 : 621;
    jy = (jy > 979) ? jy - 979 : jy;
    var days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + 78 + jd +
        ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    var gd = days + 1;
    var leap = (gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0);
    var monthDays = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var gm;
    for (gm = 0; gm < 13 && gd > monthDays[gm]; gm++) gd -= monthDays[gm];
    var p2 = function (n) { return (n < 10 ? '0' : '') + n; };
    return gy + '-' + p2(gm) + '-' + p2(gd);
}
// function jalaliToGregorian(jy, jm, jd) {
//     try {
//         const PERSIAN_EPOCH = 1948321; // Julian day of 1/1/1 Jalali
        
//         let epyear, epday;
        
//         if (jy >= 0) {
//             epyear = jy;
//         } else {
//             epyear = jy;
//         }
        
//         let aux1 = 0;
//         let aux2 = 0;
        
//         if (epyear >= 0) {
//             aux1 = 683 * (epyear / 33);
//             aux1 = Math.floor(aux1);
//             aux2 = epyear % 33;
//             aux1 = aux1 + Math.floor((aux2 + 3) / 4);
//             if (((aux2 % 4) == 0) && (aux2 != 0)) {
//                 aux1 = aux1 + 1;
//             }
//         } else {
//             aux1 = Math.floor(-683 * ((-epyear) / 33));
//             aux2 = (-epyear) % 33;
//             aux1 = aux1 - Math.floor((aux2 + 3) / 4);
//             if (((aux2 % 4) == 1) && (aux2 != 1)) {
//                 aux1 = aux1 - 1;
//             }
//         }
        
//         if (jm <= 6) {
//             epday = (jm - 1) * 31 + jd;
//         } else {
//             epday = (jm - 7) * 30 + jd + 186;
//         }
        
//         const julianDay = aux1 + 365 * epyear + epday + PERSIAN_EPOCH - 1;
        
//         // تبدیل Julian Day به تاریخ میلادی
//         const a = julianDay + 32044;
//         const b = Math.floor((4 * a + 3) / 146097);
//         const c = a - Math.floor((146097 * b) / 4);
//         const d = Math.floor((4 * c + 3) / 1461);
//         const e = c - Math.floor((1461 * d) / 4);
//         const m = Math.floor((5 * e + 2) / 153);
        
//         const day = e - Math.floor((153 * m + 2) / 5) + 1;
//         const month = m + 3 - 12 * Math.floor(m / 10);
//         const year = 100 * b + d - 4800 + Math.floor(m / 10);
        
//         // فرمت YYYY-MM-DD
//         return `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        
//     } catch (error) {
//         console.error('Error in Jalali to Gregorian conversion:', error);
//         return '';
//     }
// }
/**
 * فرمت کردن تاریخ شمسی
 * @param {string} dateString - تاریخ میلادی
 * @param {string} format - فرمت خروجی (مثل 'YYYY/MM/DD' یا 'DD MMMM YYYY')
 * @returns {string} - تاریخ فرمت شده
 */
function formatPersianDate(dateString) {
    if (!dateString) return 'نامشخص';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'نامشخص';
        
        // استفاده از Intl.DateTimeFormat برای تبدیل صحیح
        const formatter = new Intl.DateTimeFormat('fa-IR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            calendar: 'persian',
            numberingSystem: 'persian'
        });
        
        return formatter.format(date);
    } catch (error) {
        console.error('Error formatting Persian date:', error);
        return dateString;
    }
}

/**
 * فرمت کردن تاریخ و زمان شمسی
 * @param {string} dateTimeString - تاریخ و زمان میلادی
 * @returns {string} - تاریخ و زمان فرمت شده
 */
function formatPersianDateTime(dateTimeString) {
    if (!dateTimeString) return 'نامشخص';
    
    try {
        const date = new Date(dateTimeString);
        if (isNaN(date.getTime())) return 'نامشخص';
        
        const formatter = new Intl.DateTimeFormat('fa-IR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            calendar: 'persian',
            numberingSystem: 'persian'
        });
        
        return formatter.format(date);
    } catch (error) {
        console.error('Error formatting Persian date-time:', error);
        return dateTimeString;
    }
}

/**
 * دریافت تاریخ امروز به شمسی
 * @param {string} format - فرمت خروجی
 * @returns {string} - تاریخ امروز
 */
// function getTodayPersian(format = 'YYYY/MM/DD') {
//     const today = new Date();
    
//     if (format === 'YYYY/MM/DD') {
//         return today.toLocaleDateString('fa-IR');
//     } else {
//         return formatPersianDate(today.toISOString(), format);
//     }
// }
function getTodayPersian() {
    const today = new Date();
    const formatter = new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        calendar: 'persian'
    });
    
    return formatter.format(today).replace(/\//g, '/');
}
/**
 * محاسبه تفاوت روزها
 * @param {string} date1 - تاریخ اول
 * @param {string} date2 - تاریخ دوم
 * @returns {number} - تفاوت به روز
 */
function dateDifferenceInDays(date1, date2) {
    const d1 = new Date(date1);
    const d2 = new Date(date2);
    const timeDiff = Math.abs(d2.getTime() - d1.getTime());
    return Math.ceil(timeDiff / (1000 * 3600 * 24));
}

/**
 * راه‌اندازی تقویم شمسی با تنظیمات صحیح
 */
function initPersianDatePicker(selector = '.persian-date', options = {}) {
    const defaultOptions = {
        format: 'YYYY/MM/DD',
        initialValue: false,
        observer: true,
        altField: selector + '-alt',
        altFormat: 'YYYY-MM-DD',
        calendar: {
            persian: {
                locale: 'fa'
            }
        },
        navigator: {
            enabled: true
        },
        toolbox: {
            enabled: true
        },
        onSelect: function(unixDate) {
            // تبدیل به تاریخ میلادی و ذخیره در فیلد مخفی
            // روز تقویمی محلیِ انتخاب‌شده (toISOString به گرینویچ می‌برد و در تهران روز قبل می‌شد)
            const __d = new Date(unixDate);
            const gregorianDate = __d.getFullYear() + '-' + String(__d.getMonth() + 1).padStart(2, '0') + '-' + String(__d.getDate()).padStart(2, '0');
            const altField = document.querySelector(this.altField);
            if (altField) {
                altField.value = gregorianDate;
            }
        },
        ...options
    };
    
    // بررسی وجود کتابخانه
    if (typeof $ !== 'undefined' && $.fn.pDatepicker) {
        $(selector).pDatepicker(defaultOptions);
    } else {
        console.error('Persian DatePicker library not found');
    }
}

/**
 * اعتبارسنجی تاریخ شمسی
 * @param {string} dateString - تاریخ شمسی
 * @returns {boolean} - معتبر بودن تاریخ
 */
function isValidPersianDate(dateString) {
    if (!dateString) return false;
    
    const pattern = /^(\d{4})\/(\d{2})\/(\d{2})$/;
    const match = dateString.match(pattern);
    
    if (!match) return false;
    
    const year = parseInt(match[1]);
    const month = parseInt(match[2]);
    const day = parseInt(match[3]);
    
    // بررسی محدوده‌های معقول
    if (year < 1300 || year > 1500) return false;
    if (month < 1 || month > 12) return false;
    if (day < 1 || day > 31) return false;
    
    // بررسی روزهای ماه
    if (month <= 6 && day > 31) return false;
    if (month > 6 && month < 12 && day > 30) return false;
    if (month === 12 && day > 29) return false;
    
    return true;
}

// صادر کردن توابع برای استفاده در سایر فایل‌ها
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        convertToJalali,
        convertToGregorian,
        formatPersianDate,
        formatPersianDateTime,
        getTodayPersian,
        dateDifferenceInDays,
        initPersianDatePicker,
        isValidPersianDate
    };
}