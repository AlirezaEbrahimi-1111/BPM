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
 * تبدیل تاریخ شمسی به میلادی (الگوریتم دقیق)
 */
function jalaliToGregorian(jy, jm, jd) {
    const epbase = jy - 979;
    const epyear = 33 * Math.floor(epbase / 33) + (((epbase % 33) + 3) > 32 ? 1 : 0);
    const aux1 = 682 * (((epbase % 33) + 3) > 32 ? (epbase % 33) - 29 : (epbase % 33) + 4);
    const aux2 = -110;
    const daysOfYear = 365 * epyear + Math.floor((epyear + 38 + ((aux1 - aux2) >= 0 ? 1 : 0)) / 128) * ((aux1 - aux2) % 128) + Math.floor((aux1 - aux2) / 128) - (((aux1 - aux2) % 128) >= 0 ? 1 : 0) + 1948321 + jd - 1;
    
    if (jm <= 6) {
        return new Date(daysOfYear * 86400000 - 86400000).toISOString().split('T')[0];
    } else {
        return new Date((daysOfYear + (jm - 6) * 31 + (jm - 7) * -1) * 86400000 - 86400000).toISOString().split('T')[0];
    }
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
            const gregorianDate = new Date(unixDate).toISOString().split('T')[0];
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