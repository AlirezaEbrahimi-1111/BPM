class PersianDatePicker {
    constructor(element) {
        this.element = element;
        this.input = element.querySelector('.persian-datepicker-input');
        this.calendar = element.querySelector('.persian-datepicker');
        this.daysContainer = element.querySelector('.datepicker-days');
        this.currentDisplay = element.querySelector('.datepicker-current');

        this.currentYear = null;
        this.currentMonth = null;
        this.selectedDate = null;
        // خواندن محدودیت از data-attribute
        this.restrictPastDays = parseInt(element.dataset.restrictPast) || 0; // 0 یعنی بدون محدودیت
        this.holidays = [];        // لیست تاریخ‌های تعطیل (فرمت: 'YYYY-MM-DD' میلادی)
        this.holidayTitles = {};   // نگاشت تاریخ → عنوان تعطیل
        this.init();
    }

    init() {
        const today = this.gregorianToJalali(new Date());
        this.currentYear = today.year;
        this.currentMonth = today.month;

        this.input.addEventListener('click', () => this.show());
        document.addEventListener('click', (e) => this.handleOutsideClick(e));

        this.calendar.querySelector('[data-action="prev"]').addEventListener('click', (e) => {
            e.stopPropagation();
            this.prevMonth();
        });
        this.calendar.querySelector('[data-action="next"]').addEventListener('click', (e) => {
            e.stopPropagation();
            this.nextMonth();
        });
        this.calendar.querySelector('.datepicker-today-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.selectToday();
        });

        this.loadHolidays().then(() => this.render());
    }

    async loadHolidays() {
        try {
            // توکن از localStorage یا window.authToken
            const token = window.authToken
                || localStorage.getItem('auth_token')
                || localStorage.getItem('token')
                || '';
    
            const res = await fetch('/api/holidays/list.php', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && Array.isArray(data.holidays)) {
                this.holidays = data.holidays.map(h => h.holiday_date); // 'YYYY-MM-DD'
                data.holidays.forEach(h => {
                    this.holidayTitles[h.holiday_date] = h.title;
                });
            }
        } catch (e) {
            // بدون تعطیلات ادامه می‌دیم
        }
    }

    show() {
        this.calendar.classList.add('show');
    }

    hide() {
        this.calendar.classList.remove('show');
    }

    handleOutsideClick(e) {
        if (!this.element.contains(e.target)) {
            this.hide();
        }
    }

    prevMonth() {
        this.currentMonth--;
        if (this.currentMonth < 1) {
            this.currentMonth = 12;
            this.currentYear--;
        }
        this.render();
    }

    nextMonth() {
        this.currentMonth++;
        if (this.currentMonth > 12) {
            this.currentMonth = 1;
            this.currentYear++;
        }
        this.render();
    }

    selectToday() {
        const today = this.gregorianToJalali(new Date());
        this.selectDate(today.year, today.month, today.day);
    }

    selectDate(year, month, day, skipConfirm = false) {
        const gregDate = this.jalaliToGregorian(year, month, day);
        const gregStr  = `${gregDate.year}-${String(gregDate.month).padStart(2,'0')}-${String(gregDate.day).padStart(2,'0')}`;
        const isFriday  = new Date(gregStr).getDay() === 5;
        const isHoliday = this.holidays.includes(gregStr);
    
        if (!skipConfirm && (isFriday || isHoliday)) {
            const label = isHoliday
                ? (this.holidayTitles[gregStr] || 'تعطیل رسمی')
                : 'جمعه';
            uiConfirm(
                `روز انتخابی (${this.formatDate(year, month, day)}) «${label}» است.\nآیا مطمئن هستید؟`,
                () => this.selectDate(year, month, day, true)
            );
            return;
        }

        this.selectedDate = { year, month, day };
        const formatted = this.formatDate(year, month, day);
        const gregorian = this.jalaliToGregorian(year, month, day);
    
        this.input.value = formatted;
        this.input.setAttribute('data-date', gregStr);
    
        const event = new Event('change', { bubbles: true });
        this.input.dispatchEvent(event);
    
        this.render();
        this.hide();
    }

    render() {
        const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        this.currentDisplay.textContent = `${monthNames[this.currentMonth - 1]} ${this.toPersianNumber(this.currentYear)}`;

        const daysInMonth = this.getDaysInMonth(this.currentYear, this.currentMonth);
        const firstDayOfWeek = this.getFirstDayOfWeek(this.currentYear, this.currentMonth);

        // تاریخ امروز به شمسی
        const todayJalali = this.gregorianToJalali(new Date());

        this.daysContainer.innerHTML = '';

        // روزهای خالی ابتدای ماه
        for (let i = 0; i < firstDayOfWeek; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'datepicker-day other-month';
            this.daysContainer.appendChild(emptyDay);
        }

        // روزهای ماه
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'datepicker-day';
            dayElement.textContent = this.toPersianNumber(day);

            const currentDayNumber = this.currentYear * 10000 + this.currentMonth * 100 + day;

            // اگر محدودیت فعال باشد
            if (this.restrictPastDays >= 0) {
                // محاسبه تاریخ حداقل مجاز (امروز منهای restrictPastDays روز)
                const minDate = new Date();
                minDate.setDate(minDate.getDate() - this.restrictPastDays);
                const minJalali = this.gregorianToJalali(minDate);
                const minJalaliNumber = minJalali.year * 10000 + minJalali.month * 100 + minJalali.day;

                
                if (currentDayNumber < minJalaliNumber) {
                    dayElement.classList.add('disabled');
                    dayElement.title = 'انتخاب این تاریخ مجاز نیست';
                } else {
                    dayElement.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.selectDate(this.currentYear, this.currentMonth, day);
                    });
                    dayElement.style.cursor = 'pointer';
                }
            } else {
                // بدون محدودیت → همه روزها قابل کلیک
                dayElement.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectDate(this.currentYear, this.currentMonth, day);
                });
                dayElement.style.cursor = 'pointer';
            }
            
            // ── تعیین تعطیل بودن روز ──────────────────────────────────────
            const gregDate = this.jalaliToGregorian(this.currentYear, this.currentMonth, day);
            const gregStr  = `${gregDate.year}-${String(gregDate.month).padStart(2,'0')}-${String(gregDate.day).padStart(2,'0')}`;
            const isFriday = new Date(gregStr).getDay() === 5; // جمعه
            const isHoliday = this.holidays.includes(gregStr);
            
            if (isFriday) dayElement.classList.add('datepicker-friday');
            if (isHoliday) {
                dayElement.classList.add('datepicker-holiday');
                dayElement.title = this.holidayTitles[gregStr] || 'تعطیل رسمی';
            }
            dayElement.dataset.gregDate = gregStr; // برای استفاده در selectDate
            // ──────────────────────────────────────────────────────────────
            
            // امروز
            if (todayJalali.year === this.currentYear &&
                todayJalali.month === this.currentMonth &&
                todayJalali.day === day) {
                dayElement.classList.add('today');
            }

            // انتخاب شده
            if (this.selectedDate &&
                this.selectedDate.year === this.currentYear &&
                this.selectedDate.month === this.currentMonth &&
                this.selectedDate.day === day) {
                dayElement.classList.add('selected');
            }

            this.daysContainer.appendChild(dayElement);
        }
    }

    getDaysInMonth(year, month) {
        if (month <= 6) return 31;
        if (month <= 11) return 30;
        return this.isLeapYear(year) ? 30 : 29;
    }

    getFirstDayOfWeek(year, month) {
        const gregorian = this.jalaliToGregorian(year, month, 1);
        const date = new Date(gregorian.year, gregorian.month - 1, gregorian.day);
        return (date.getDay() + 1) % 7; // تبدیل به شنبه = 0
    }

    isLeapYear(year) {
        const breaks = [1, 5, 9, 13, 17, 22, 26, 30];
        const gy = year + 621;
        let jp = breaks[0];

        let jump = 0;
        for (let i = 1; i < breaks.length; i++) {
            const jm = breaks[i];
            jump = jm - jp;
            if (year < jm) break;
            jp = jm;
        }

        let n = year - jp;
        if (jump - n < 6) n = n - jump + ((jump + 4) / 33) * 33;

        let leap = ((n + 1) % 33) - 1;
        if (leap === -1) leap = 32;

        return (leap % 4 === 0);
    }

    gregorianToJalali(date) {
        let gy = date.getFullYear();
        let gm = date.getMonth() + 1;
        let gd = date.getDate();

        const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        let jy = (gy <= 1600) ? 0 : 979;

        gy -= (gy <= 1600) ? 621 : 1600;
        let jm, jd;

        let gy2 = (gm > 2) ? (gy + 1) : gy;
        let days = (365 * gy) + (parseInt((gy2 + 3) / 4)) - (parseInt((gy2 + 99) / 100)) +
            (parseInt((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];

        jy += 33 * parseInt(days / 12053);
        days %= 12053;
        jy += 4 * parseInt(days / 1461);
        days %= 1461;

        if (days > 365) {
            jy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }

        if (days < 186) {
            jm = 1 + parseInt(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + parseInt((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }

        return { year: jy, month: jm, day: jd };
    }

    jalaliToGregorian(jy, jm, jd) {
        jy += (jy < 0) ? 1 : 0;
        let gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;

        let days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 78 + jd +
            ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);

        gy += 400 * parseInt(days / 146097);
        days %= 146097;

        let leap = true;
        if (days >= 36525) {
            days--;
            gy += 100 * parseInt(days / 36524);
            days %= 36524;

            if (days >= 365) days++;
            else leap = false;
        }

        gy += 4 * parseInt(days / 1461);
        days %= 1461;

        if (days >= 366) {
            leap = false;
            days--;
            gy += parseInt(days / 365);
            days = days % 365;
        }

        const g_d_m = [0, 31, ((leap || ((gy % 100 !== 0) && (gy % 4 === 0))) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        let gm, gd;

        for (gm = 0; gm < 13 && days >= g_d_m[gm]; gm++) {
            days -= g_d_m[gm];
        }

        gd = days + 1;

        return { year: gy, month: gm, day: gd };
    }

    formatDate(year, month, day) {
        return `${this.toPersianNumber(year)}/${this.toPersianNumber(month.toString().padStart(2, '0'))}/${this.toPersianNumber(day.toString().padStart(2, '0'))}`;
    }

    toPersianNumber(num) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return num.toString().replace(/\d/g, x => persianDigits[parseInt(x)]);
    }

    getValue() {
        return this.input.getAttribute('data-date');
    }

    setValue(gregorianDate) {
        if (!gregorianDate) return;

        const date = new Date(gregorianDate);
        const jalali = this.gregorianToJalali(date);
        this.selectDate(jalali.year, jalali.month, jalali.day);
    }

    clear() {
        this.input.value = '';
        this.input.removeAttribute('data-date');
        this.selectedDate = null;
        this.render();
    }
}

// تابع راه‌اندازی خودکار
function initPersianDatepickers() {
    const datepickerElements = document.querySelectorAll('.persian-datepicker-wrapper');
    datepickerElements.forEach(element => {
        if (!element.datepickerInstance) {
            // بررسی اینکه آیا المان‌های داخلی وجود دارند
            const input = element.querySelector('.persian-datepicker-input');
            const calendar = element.querySelector('.persian-datepicker');

            if (input && calendar) {
                element.datepickerInstance = new PersianDatePicker(element);
            } else {
                console.warn('تقویم ناقص است:', element);
            }
        }
    });
}

// راه‌اندازی خودکار در زمان لود صفحه
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPersianDatepickers);
} else {
    initPersianDatepickers();
}

// راه‌اندازی مجدد برای المان‌های پویا
// این تابع را می‌توان بعد از نمایش المان‌های مخفی فراخوانی کرد
window.reinitPersianDatepickers = function () {
    initPersianDatepickers();
};

// Export برای استفاده در ماژول‌ها
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PersianDatePicker;
}