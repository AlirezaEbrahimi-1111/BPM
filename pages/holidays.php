<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me || !hasPermission($__me, 'view_org_settings')) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت روزهای تعطیل - یکتا همراهان ملک</title>
    <link href="<?= asset('../assets/js/cdn/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/fonts/Vazirmatn-font-face.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-calendar-x"></i>
                مدیریت روزهای تعطیل
            </h1>
        </div>

        <div class="row g-4">
            <!-- Calendar -->
            <div class="col-lg-8">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <button class="calendar-nav-btn" onclick="changeMonth(-1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <h3 class="calendar-title" id="calendarTitle">در حال بارگذاری...</h3>
                        <div class="calendar-nav">
                            <button class="calendar-nav-btn" onclick="changeMonth(1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                        </div>
                    </div>

                    <div class="calendar-grid" id="calendarGrid">
                        <!-- روزهای هفته -->
                        <div class="calendar-day-name">شنبه</div>
                        <div class="calendar-day-name">یکشنبه</div>
                        <div class="calendar-day-name">دوشنبه</div>
                        <div class="calendar-day-name">سه‌شنبه</div>
                        <div class="calendar-day-name">چهارشنبه</div>
                        <div class="calendar-day-name">پنج‌شنبه</div>
                        <div class="calendar-day-name friday">جمعه</div>
                    </div>

                    <div class="calendar-legend">
                        <div class="legend-item">
                            <div class="legend-color friday"></div>
                            <span>جمعه (تعطیل پیش‌فرض)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color holiday"></div>
                            <span>تعطیل رسمی</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color today"></div>
                            <span>امروز</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Holidays List -->
            <div class="col-lg-4">
                <div class="holidays-list-container">
                    <div class="holidays-list-header">
                        <h4 class="holidays-list-title">
                            <i class="bi bi-list-ul"></i>
                            تعطیلات این ماه
                        </h4>
                    </div>
                    <div id="holidaysList">
                        <div class="no-holidays">
                            <i class="bi bi-calendar-check"></i>
                            <div>در حال بارگذاری...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal افزودن تعطیل -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>
                        افزودن روز تعطیل
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addHolidayForm">
                        <input type="hidden" id="holidayDate" name="holiday_date">

                        <!-- موقتاً غیرفعال: انتخابِ نوعِ تعطیلی (روزِ مشخص/هفتگی) — فقط روزِ
                             مشخص فعاله؛ برایِ برگردوندنش، این input رو به همون <select> قبلی
                             (با id="holidayType" و onchange="onHolidayTypeChange()") برگردونید -->
                        <input type="hidden" id="holidayType" value="date">

                        <div class="mb-3" id="holidayDateField">
                            <label class="form-label">تاریخ انتخاب شده</label>
                            <input type="text" class="form-control" id="holidayDateDisplay" readonly>
                        </div>

                        <div class="mb-3" id="holidayWeekdayField" style="display:none;">
                            <label class="form-label">روزِ هفته</label>
                            <select class="form-select" id="holidayWeekday">
                                <option value="6">شنبه</option>
                                <option value="0">یکشنبه</option>
                                <option value="1">دوشنبه</option>
                                <option value="2">سه‌شنبه</option>
                                <option value="3">چهارشنبه</option>
                                <option value="4">پنج‌شنبه</option>
                            </select>
                        </div>

                        <?php if ((int) $user_id === 1): ?>
                        <div class="mb-3">
                            <label class="form-label">دامنه</label>
                            <select class="form-select" id="holidayScope">
                                <option value="org">فقط سازمانِ من</option>
                                <option value="global">سراسری (همهٔ سازمان‌ها)</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" id="holidayScope" value="org">
                        <div class="mb-3 text-muted" style="font-size:.85rem;">
                            <i class="bi bi-info-circle"></i>
                            این تعطیلی فقط برایِ سازمانِ شما اعمال می‌شود.
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">عنوان تعطیلی</label>
                            <input type="text" class="form-control" id="holidayTitle" name="title"
                                placeholder="مثال: عید نوروز" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات (اختیاری)</label>
                            <textarea class="form-control" id="holidayDescription" name="description" rows="2"
                                placeholder="توضیحات اضافی..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" onclick="saveHoliday()">
                        <i class="bi bi-check-lg me-1"></i>
                        ذخیره
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <script src="<?= asset('../../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        // ============================================
        // متغیرهای سراسری - استفاده از authToken تعریف شده در header
        // ============================================
        let currentYear, currentMonth; // شمسی
        let holidays = {}; // dateStr -> {id, title, type, is_global, can_delete, day_of_week}
        const persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        const persianWeekdayNames = { 0: 'یکشنبه', 1: 'دوشنبه', 2: 'سه‌شنبه', 3: 'چهارشنبه', 4: 'پنج‌شنبه', 5: 'جمعه', 6: 'شنبه' };

        function toFa(n) {
            if (n === null || n === undefined || n === '') return '';
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        function onHolidayTypeChange() {
            const isWeekly = document.getElementById('holidayType').value === 'weekly';
            document.getElementById('holidayDateField').style.display = isWeekly ? 'none' : '';
            document.getElementById('holidayWeekdayField').style.display = isWeekly ? '' : 'none';
        }

        // ============================================
        // توابع تبدیل تاریخ
        // ============================================
        function gregorianToJalali(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            gy -= (gy <= 1600) ? 621 : 1600;
            const gy2 = (gm > 2) ? (gy + 1) : gy;
            let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
                Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
            const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return [jy, jm, jd];
        }

        function jalaliToGregorian(jy, jm, jd) {
            let gy = (jy < 979) ? 621 : 1600;
            if (jy >= 979) jy -= 979;
            let days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + 78 + jd;
            if (jm < 7) days += (jm - 1) * 31;
            else days += ((jm - 7) * 30) + 186;
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
            let gd = days + 1;
            const leap = ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0));
            const sal_a = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            let gm;
            for (gm = 0; gm < 13; gm++) {
                if (gd <= sal_a[gm]) break;
                gd -= sal_a[gm];
            }
            return [gy, gm, gd];
        }

        function getJalaliMonthDays(year, month) {
            if (month <= 6) return 31;
            if (month <= 11) return 30;
            // اسفند
            const isLeap = ((year - (year > 0 ? 474 : 473)) % 2820 + 474 + 38) * 682 % 2816 < 682;
            return isLeap ? 30 : 29;
        }

        function getFirstDayOfMonth(jy, jm) {
            const [gy, gm, gd] = jalaliToGregorian(jy, jm, 1);
            const d = new Date(gy, gm - 1, gd);
            let dow = d.getDay(); // 0=Sunday
            // تبدیل به شمسی (شنبه=0)
            return (dow + 1) % 7;
        }

        // ============================================
        // رندر تقویم
        // ============================================
        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const title = document.getElementById('calendarTitle');

            title.textContent = `${persianMonths[currentMonth - 1]} ${toFa(currentYear)}`;

            // پاک کردن روزها (نگه داشتن هدرها)
            const dayNames = grid.querySelectorAll('.calendar-day-name');
            grid.innerHTML = '';
            dayNames.forEach(dn => grid.appendChild(dn));

            const daysInMonth = getJalaliMonthDays(currentYear, currentMonth);
            const firstDay = getFirstDayOfMonth(currentYear, currentMonth);

            // امروز
            const today = new Date();
            const [todayJY, todayJM, todayJD] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

            // روزهای خالی قبل از اول ماه
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                grid.appendChild(emptyDay);
            }

            // روزهای ماه
            for (let day = 1; day <= daysInMonth; day++) {
                const dayEl = document.createElement('div');
                dayEl.className = 'calendar-day';

                // تبدیل به میلادی
                const [gy, gm, gd] = jalaliToGregorian(currentYear, currentMonth, day);
                const dateStr = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
                const gDate = new Date(gy, gm - 1, gd);
                const isFriday = gDate.getDay() === 5;

                // بررسی تعطیل
                const isHoliday = holidays[dateStr];
                const isToday = (currentYear === todayJY && currentMonth === todayJM && day === todayJD);

                if (isFriday) dayEl.classList.add('friday');
                if (isHoliday) dayEl.classList.add('holiday');
                if (isToday) dayEl.classList.add('today');

                dayEl.innerHTML = `
                    <span class="day-number">${toFa(day)}</span>
                    ${isHoliday ? `<span class="day-label">${isHoliday.title}${isHoliday.type === 'weekly' ? ' 🔁' : ''}</span>` : ''}
                    ${isHoliday ? '<div class="holiday-indicator"></div>' : ''}
                `;

                dayEl.dataset.date = dateStr;
                dayEl.dataset.jalali = `${currentYear}/${String(currentMonth).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
                dayEl.dataset.isFriday = isFriday;

                dayEl.onclick = () => handleDayClick(dateStr, dayEl.dataset.jalali, isFriday, isHoliday);

                grid.appendChild(dayEl);
            }
        }

        // ============================================
        // رندر لیست تعطیلات
        // ============================================
        function renderHolidaysList() {
            const list = document.getElementById('holidaysList');

            // فیلتر تعطیلات این ماه
            const monthHolidays = [];
            for (const [date, h] of Object.entries(holidays)) {
                const [gy, gm, gd] = date.split('-').map(Number);
                const [jy, jm, jd] = gregorianToJalali(gy, gm, gd);
                if (jy === currentYear && jm === currentMonth) {
                    monthHolidays.push({
                        ...h,
                        date: date,
                        jalali: `${toFa(jy)}/${toFa(String(jm).padStart(2, '0'))}/${toFa(String(jd).padStart(2, '0'))}`,
                        day: jd
                    });
                }
            }

            // مرتب‌سازی
            monthHolidays.sort((a, b) => a.day - b.day);

            if (monthHolidays.length === 0) {
                list.innerHTML = `
                    <div class="no-holidays">
                        <i class="bi bi-calendar-check"></i>
                        <div>هیچ تعطیلی در این ماه ثبت نشده</div>
                    </div>
                `;
                return;
            }

            list.innerHTML = monthHolidays.map(h => `
                <div class="holiday-item">
                    <div class="holiday-info">
                        <span class="holiday-date">${h.jalali}${h.type === 'weekly' ? ' (هر ' + persianWeekdayNames[h.day_of_week] + ')' : ''}</span>
                        <span class="holiday-title">
                            ${h.title}
                            <span class="badge ${h.is_global ? 'bg-primary' : 'bg-secondary'}" style="font-size:.65rem;">
                                ${h.is_global ? 'سراسری' : 'سازمانِ من'}
                            </span>
                        </span>
                    </div>
                    ${h.can_delete ? `
                    <button class="holiday-delete-btn" onclick="deleteHoliday(${h.id}, ${h.type === 'weekly'})" title="حذف">
                        <i class="bi bi-trash"></i>
                    </button>` : ''}
                </div>
            `).join('');
        }

        // ============================================
        // تغییر ماه
        // ============================================
        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            } else if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            loadHolidays();
        }

        // ============================================
        // کلیک روی روز
        // ============================================
        function handleDayClick(dateStr, jalaliStr, isFriday, existingHoliday) {
            if (existingHoliday) {
                if (!existingHoliday.can_delete) {
                    showToast('شما اجازهٔ حذفِ این تعطیلی را ندارید', 'info');
                    return;
                }
                // حذف تعطیل (تأیید داخل خود deleteHoliday انجام می‌شود)
                deleteHoliday(existingHoliday.id, existingHoliday.type === 'weekly');
            } else if (!isFriday) {
                // افزودن تعطیل جدید
                document.getElementById('holidayType').value = 'date';
                onHolidayTypeChange();
                document.getElementById('holidayDate').value = dateStr;
                document.getElementById('holidayDateDisplay').value = toFa(jalaliStr);
                document.getElementById('holidayTitle').value = '';
                document.getElementById('holidayDescription').value = '';
                new bootstrap.Modal(document.getElementById('addHolidayModal')).show();
            } else {
                showToast('جمعه‌ها پیش‌فرض تعطیل هستند', 'info');
            }
        }

        // ============================================
        // بارگذاری تعطیلات
        // ============================================
        async function loadHolidays() {
            showLoading(true);
            try {
                // محاسبه بازه میلادی برای ماه شمسی جاری
                const [startGY, startGM, startGD] = jalaliToGregorian(currentYear, currentMonth, 1);
                const daysInMonth = getJalaliMonthDays(currentYear, currentMonth);
                const [endGY, endGM, endGD] = jalaliToGregorian(currentYear, currentMonth, daysInMonth);

                const startDate = `${startGY}-${String(startGM).padStart(2, '0')}-${String(startGD).padStart(2, '0')}`;
                const endDate = `${endGY}-${String(endGM).padStart(2, '0')}-${String(endGD).padStart(2, '0')}`;

                const response = await fetch(`/api/holidays/list.php?start_date=${startDate}&end_date=${endDate}`, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });

                const data = await response.json();

                if (data.success) {
                    holidays = {};
                    data.holidays.forEach(h => {
                        holidays[h.holiday_date] = h;
                    });
                }
            } catch (error) {
                console.error('Error loading holidays:', error);
            } finally {
                showLoading(false);
                renderCalendar();
                renderHolidaysList();
            }
        }

        // ============================================
        // ذخیره تعطیل جدید
        // ============================================
        async function saveHoliday() {
            const type = document.getElementById('holidayType').value;
            const date = document.getElementById('holidayDate').value;
            const dayOfWeek = document.getElementById('holidayWeekday').value;
            const scope = document.getElementById('holidayScope').value;
            const title = document.getElementById('holidayTitle').value.trim();
            const description = document.getElementById('holidayDescription').value.trim();

            if (!title) {
                alert('لطفاً عنوان تعطیلی را وارد کنید');
                return;
            }
            if (type === 'date' && !date) {
                alert('لطفاً یک تاریخ از روی تقویم انتخاب کنید');
                return;
            }

            const payload = {
                type: type,
                scope: scope,
                title: title,
                description: description
            };
            if (type === 'weekly') {
                payload.day_of_week = parseInt(dayOfWeek, 10);
            } else {
                payload.holiday_date = date;
            }

            showLoading(true);
            try {
                const response = await fetch('/api/holidays/add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addHolidayModal')).hide();
                    showToast('✅ روز تعطیل با موفقیت اضافه شد', 'success');
                    loadHolidays();
                } else {
                    alert('خطا: ' + (data.message || 'نامشخص'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            } finally {
                showLoading(false);
            }
        }

        // ============================================
        // حذف تعطیل
        // ============================================
        function deleteHoliday(id, isWeekly) {
            const confirmMsg = isWeekly
                ? 'این یک تعطیلیِ هفتگیِ تکرارشونده است — با حذف، همهٔ روزهای آینده هم دیگر تعطیل حساب نمی‌شوند. آیا مطمئنید؟'
                : 'آیا از حذف این روز تعطیل اطمینان دارید؟';
            uiConfirm(confirmMsg, async function () {
                showLoading(true);
                try {
                    const response = await fetch('/api/holidays/delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({ id: id })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast('✅ روز تعطیل حذف شد', 'success');
                        loadHolidays();
                    } else {
                        alert('خطا: ' + (data.message || 'نامشخص'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('خطا در ارتباط با سرور');
                } finally {
                    showLoading(false);
                }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        }

        // ============================================
        // توابع کمکی
        // ============================================
        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function showToast(message, type = 'success') {
            const existing = document.querySelector('.custom-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = `custom-toast toast-${type}`;
            toast.style.cssText = `
                position: fixed; top: 100px; right: 20px; min-width: 280px;
                background: var(--surface); color: var(--text-strong); border-radius: 12px; padding: 16px 20px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.15); z-index: 10000;
                border-left: 4px solid ${type === 'success' ? '#10b981' : '#3b82f6'};
                transform: translateX(400px); transition: transform 0.4s ease;
            `;
            toast.innerHTML = `<div style="display:flex;align-items:center;gap:10px;font-weight:600;">
                <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'info-circle-fill'}" 
                   style="color:${type === 'success' ? '#10b981' : '#3b82f6'};font-size:1.2rem;"></i>
                <span>${message}</span>
            </div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.style.transform = 'translateX(0)', 50);
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        // ============================================
        // شروع
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            // تنظیم ماه و سال جاری شمسی
            const today = new Date();
            const [jy, jm, jd] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
            currentYear = jy;
            currentMonth = jm;

            loadHolidays();
        });
    </script>
</body>

</html>