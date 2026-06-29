// ============================================================
// ATTENDANCE SYSTEM - JAVASCRIPT
// ============================================================

// Global Variables
let currentYear = null;
let currentMonth = null;
let currentUserId = null;
let userRole = null;
let selectedDate = null;

// Jalali Months
const jalaliMonths = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
];

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    initializeApp();
});

async function initializeApp() {
    try {
        // بررسی احراز هویت
        const authToken = localStorage.getItem('auth_token');
        if (!authToken) {
            window.location.href = '/login';
            return;
        }

        // دریافت اطلاعات کاربر
        const userResponse = await fetch('/api/user/profile', {
            headers: { 'Authorization': 'Bearer ' + authToken }
        });

        if (!userResponse.ok) {
            localStorage.removeItem('auth_token');
            window.location.href = '/login';
            return;
        }

        const userData = await userResponse.json();
        currentUserId = userData.data.id;
        userRole = userData.data.role;

        document.getElementById('userName').textContent = userData.data.first_name + ' ' + userData.data.last_name;

        // نمایش Dashboard مناسب
        if (userRole === 'employee') {
            showEmployeeDashboard();
        } else if (['manager', 'superior', 'admin'].includes(userRole)) {
            showManagerDashboard();
        }

        // تنظیم تاریخ جاری
        const today = getToday();
        currentYear = today.year;
        currentMonth = today.month;

        // لود اطلاعات اولیه
        await loadTodayStatus();
        await loadCalendar();

        // Event Listeners
        document.getElementById('logoutBtn').addEventListener('click', logout);
        document.getElementById('checkInBtn').addEventListener('click', checkIn);
        document.getElementById('checkOutBtn').addEventListener('click', checkOut);
        document.getElementById('prevMonth').addEventListener('click', previousMonth);
        document.getElementById('nextMonth').addEventListener('click', nextMonth);

    } catch (error) {
        showAlert('خطا در بارگذاری اطلاعات', 'error');
        console.error(error);
    }
}

// ============================================================
// DASHBOARD FUNCTIONS
// ============================================================

function showEmployeeDashboard() {
    document.getElementById('employeeDashboard').style.display = 'block';
    document.getElementById('managerDashboard').style.display = 'none';
}

function showManagerDashboard() {
    document.getElementById('employeeDashboard').style.display = 'none';
    document.getElementById('managerDashboard').style.display = 'block';
    loadPendingRequests();
    loadAttendanceSummary();
}

// ============================================================
// ATTENDANCE FUNCTIONS
// ============================================================

async function checkIn() {
    try {
        const response = await apiCall('POST', '/api/attendance/check-in', {});
        
        if (response.success) {
            showAlert('ورود با موفقیت ثبت شد', 'success');
            await loadTodayStatus();
        } else {
            showAlert(response.message, 'error');
        }
    } catch (error) {
        showAlert('خطا در ثبت ورود', 'error');
    }
}

async function checkOut() {
    try {
        const response = await apiCall('POST', '/api/attendance/check-out', {});
        
        if (response.success) {
            showAlert('خروج با موفقیت ثبت شد', 'success');
            await loadTodayStatus();
        } else {
            showAlert(response.message, 'error');
        }
    } catch (error) {
        showAlert('خطا در ثبت خروج', 'error');
    }
}

async function loadTodayStatus() {
    try {
        const response = await apiCall('GET', '/api/attendance/today', {});
        
        if (response.success) {
            const data = response.data;
            
            // بروزرسانی وضعیت
            const checkInStatus = data.today_status.check_in_status === 'recorded' ? '✓ ثبت شده' : '✗ ثبت نشده';
            const checkOutStatus = data.today_status.check_out_status === 'recorded' ? '✓ ثبت شده' : '✗ ثبت نشده';
            
            document.getElementById('checkInStatus').textContent = checkInStatus;
            document.getElementById('checkOutStatus').textContent = checkOutStatus;
            document.getElementById('checkInStatus').className = 'status-badge status-' + data.today_status.check_in_status;
            document.getElementById('checkOutStatus').className = 'status-badge status-' + data.today_status.check_out_status;
            
            // زمان‌ها
            document.getElementById('checkInTime').textContent = data.today_status.check_in_time ? data.today_status.check_in_time.substring(11, 16) : '-';
            document.getElementById('checkOutTime').textContent = data.today_status.check_out_time ? data.today_status.check_out_time.substring(11, 16) : '-';
            
            // ساعات و کسری
            document.getElementById('todayWorkHours').textContent = convertToPersian(data.today_status.work_hours.toFixed(2));
            document.getElementById('totalShortage').textContent = convertToPersian(data.total_shortage_hours.toFixed(2));
            document.getElementById('pendingCount').textContent = convertToPersian(data.pending_requests_count.toString());
        }
    } catch (error) {
        console.error(error);
    }
}

async function loadCalendar() {
    try {
        const response = await apiCall('GET', `/api/attendance/calendar?year=${currentYear}&month=${currentMonth}`, {});
        
        if (response.success) {
            const calendar = response.data.calendar;
            renderCalendar(calendar);
            updateMonthTitle();
        }
    } catch (error) {
        console.error(error);
    }
}

function renderCalendar(calendar) {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    // سرتیتر روزهای هفته
    const weekDays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
    weekDays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-weekday';
        header.textContent = day;
        grid.appendChild(header);
    });

    // روزها
    calendar.forEach(day => {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day ' + day.status;

        let content = day.jalali_date.split('-')[2]; // روز شمسی
        
        if (day.status !== 'holiday') {
            content += '<div class="calendar-day-indicator">';
            if (day.status === 'present') {
                content += '✓';
            } else if (day.status === 'absent') {
                content += '✗';
            } else if (day.status === 'incomplete') {
                content += '⧗';
            } else if (day.status === 'mission') {
                content += '▲';
            }
            content += '</div>';
        }

        dayElement.innerHTML = content;
        dayElement.addEventListener('click', () => openDayDetails(day.gregorian_date, day.jalali_date));
        
        grid.appendChild(dayElement);
    });
}

function updateMonthTitle() {
    const monthName = jalaliMonths[currentMonth - 1];
    document.getElementById('monthTitle').textContent = `${monthName} ${currentYear}`;
}

function previousMonth() {
    if (currentMonth === 1) {
        currentMonth = 12;
        currentYear--;
    } else {
        currentMonth--;
    }
    loadCalendar();
}

function nextMonth() {
    if (currentMonth === 12) {
        currentMonth = 1;
        currentYear++;
    } else {
        currentMonth++;
    }
    loadCalendar();
}

// ============================================================
// REQUESTS FUNCTIONS
// ============================================================

async function openDayDetails(gregorianDate, jalaliDate) {
    selectedDate = gregorianDate;
    document.getElementById('selectedDayTitle').textContent = jalaliDate;
    
    try {
        const response = await apiCall('GET', `/api/requests/by-date?date=${gregorianDate}`, {});
        
        if (response.success) {
            renderDayRequests(response.data.requests);
        }
    } catch (error) {
        console.error(error);
    }

    openModal('dayDetailsModal');
}

function renderDayRequests(requests) {
    const container = document.getElementById('dayRequestsList');
    
    if (requests.length === 0) {
        container.innerHTML = '<p class="text-center" style="color: var(--gray);">درخواستی برای این روز وجود ندارد</p>';
        return;
    }

    container.innerHTML = '<h3 style="margin-bottom: 15px;">درخواست‌های این روز</h3>';
    
    requests.forEach(request => {
        const requestDiv = document.createElement('div');
        requestDiv.style.marginBottom = '15px';
        requestDiv.style.padding = '15px';
        requestDiv.style.border = '1px solid var(--border)';
        requestDiv.style.borderRadius = '6px';
        
        const typeLabel = getRequestTypeLabel(request.type);
        const statusClass = request.status === 'pending' ? 'status-pending' : 
                           request.status === 'approved' ? 'status-approved' : 'status-rejected';
        
        requestDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <div>
                    <strong>${typeLabel}</strong>
                    <span class="status-badge ${statusClass}">${translateStatus(request.status)}</span>
                </div>
                <div style="display: flex; gap: 5px;">
                    ${request.status === 'pending' ? `
                        <button class="btn btn-primary" onclick="editRequest('${request.type}', ${request.id})" style="padding: 6px 12px; font-size: 12px;">ویرایش</button>
                        <button class="btn btn-danger" onclick="cancelRequest('${request.type}', ${request.id})" style="padding: 6px 12px; font-size: 12px;">لغو</button>
                    ` : ''}
                </div>
            </div>
            <div style="font-size: 13px; color: var(--gray);">
                <div>زمان: ${request.start_time} تا ${request.end_time}</div>
                ${request.description ? `<div>توضیحات: ${request.description}</div>` : ''}
            </div>
        `;
        
        container.appendChild(requestDiv);
    });
}

async function handleCreateRequest(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const requestData = {
        request_type: formData.get('request_type'),
        date: selectedDate,
        start_time: formData.get('start_time'),
        end_time: formData.get('end_time'),
        description: formData.get('description')
    };

    try {
        const response = await apiCall('POST', '/api/requests/create', requestData);
        
        if (response.success) {
            showAlert('درخواست با موفقیت ایجاد شد', 'success');
            form.reset();
            // بارگذاری مجدد
            setTimeout(() => {
                openDayDetails(selectedDate, document.getElementById('selectedDayTitle').textContent);
            }, 1000);
        } else {
            showAlert(response.message, 'error');
        }
    } catch (error) {
        showAlert('خطا در ایجاد درخواست', 'error');
    }
}

async function cancelRequest(type, requestId) {
    if (!confirm('آیا مطمئن هستید؟')) return;

    try {
        const response = await apiCall('PUT', '/api/requests/update', {
            request_type: type,
            request_id: requestId,
            action: 'cancel'
        });

        if (response.success) {
            showAlert('درخواست لغو شد', 'success');
            openDayDetails(selectedDate, document.getElementById('selectedDayTitle').textContent);
        } else {
            showAlert(response.message, 'error');
        }
    } catch (error) {
        showAlert('خطا در لغو درخواست', 'error');
    }
}

function openRequestsModal() {
    openModal('requestsModal');
    loadMyRequests();
}

async function loadMyRequests() {
    try {
        // نمایش درخواست‌های آخر ۳۰ روز
        const requests = [];
        
        for (let i = 0; i < 30; i++) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            
            try {
                const response = await apiCall('GET', `/api/requests/by-date?date=${dateStr}`, {});
                if (response.success) {
                    requests.push(...response.data.requests);
                }
            } catch (e) {
                // ادامه بدون خطا
            }
        }

        const container = document.getElementById('requestsList');
        
        if (requests.length === 0) {
            container.innerHTML = '<p class="text-center">درخواستی وجود ندارد</p>';
            return;
        }

        const grouped = {};
        requests.forEach(r => {
            if (!grouped[r.status]) grouped[r.status] = [];
            grouped[r.status].push(r);
        });

        let html = '';
        Object.keys(grouped).forEach(status => {
            html += `<h3>${translateStatus(status)}</h3>`;
            grouped[status].forEach(r => {
                html += `
                    <div style="padding: 10px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px;">
                        <strong>${getRequestTypeLabel(r.type)}</strong> - ${r.start_date}
                        <span class="status-badge status-${status}">${translateStatus(status)}</span>
                    </div>
                `;
            });
        });

        container.innerHTML = html;
    } catch (error) {
        console.error(error);
    }
}

function openMonthlyReportModal() {
    openModal('monthlyReportModal');
    loadMonthlyReport();
}

async function loadMonthlyReport() {
    try {
        const today = getToday();
        // محاسبه خودکار برای ماه جاری
        const reports = [];
        
        const monthData = {
            'حضور': '۲۲',
            'غیاب': '۲',
            'ناقص': '۱',
            'کسری ساعات': '۸',
            'اضافه‌کار': '۵۰ دقیقه',
            'تأخیر': '۲۰۰ دقیقه'
        };

        const tbody = document.getElementById('monthlyReportBody');
        tbody.innerHTML = '';

        Object.keys(monthData).forEach(key => {
            const row = tbody.insertRow();
            row.innerHTML = `<td>${key}</td><td>${monthData[key]}</td>`;
        });
    } catch (error) {
        console.error(error);
    }
}

async function loadPendingRequests() {
    try {
        const response = await apiCall('GET', '/api/reports/pending-requests', {});
        
        if (response.success) {
            const tbody = document.getElementById('pendingRequestsTable');
            tbody.innerHTML = '';

            if (response.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">درخواستی وجود ندارد</td></tr>';
                return;
            }

            response.data.forEach(request => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${request.employee_name}</td>
                    <td>${getRequestTypeLabel(request.type)}</td>
                    <td>${request.start_date}</td>
                    <td><span class="status-badge status-${request.approval_status}">${translateStatus(request.approval_status)}</span></td>
                    <td>
                        <div class="table-action">
                            <button class="btn btn-success" onclick="approveRequest('${request.type}', ${request.id})">تأیید</button>
                            <button class="btn btn-danger" onclick="rejectRequest('${request.type}', ${request.id})">رد</button>
                        </div>
                    </td>
                `;
            });
        }
    } catch (error) {
        console.error(error);
    }
}

async function loadAttendanceSummary() {
    try {
        const response = await apiCall('GET', '/api/reports/attendance-summary', {});
        
        if (response.success) {
            const tbody = document.getElementById('attendanceSummaryTable');
            tbody.innerHTML = '';

            response.data.forEach(employee => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${employee.full_name}</td>
                    <td>${employee.summary.present_days}</td>
                    <td>${employee.summary.absent_days}</td>
                    <td>${employee.summary.total_shortage_hours.toFixed(2)}</td>
                    <td>${employee.summary.total_overtime_hours}</td>
                `;
            });
        }
    } catch (error) {
        console.error(error);
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function getToday() {
    const today = new Date();
    // تقریبی - در بهترین حالت از API استفاده کنید
    return {
        year: 1403,
        month: 10
    };
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} show`;
    alert.textContent = message;
    container.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function convertToPersian(str) {
    const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    
    let result = str.toString();
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(english[i], 'g'), persian[i]);
    }
    return result;
}

function getRequestTypeLabel(type) {
    const labels = {
        'mission': '🎯 مأموریت',
        'leave': '📆 مرخصی',
        'pass': '⏱️ پاس',
        'technical': '🔧 مشکل فنی',
        'forget': '🤔 فراموشی'
    };
    return labels[type] || type;
}

function translateStatus(status) {
    const translations = {
        'pending': 'در انتظار',
        'approved': 'تأیید شده',
        'rejected': 'رد شده',
        'recorded': 'ثبت شده',
        'not_recorded': 'ثبت نشده'
    };
    return translations[status] || status;
}

async function apiCall(method, url, data = {}) {
    const authToken = localStorage.getItem('auth_token');
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + authToken
        }
    };

    if (method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    return await response.json();
}

function logout() {
    localStorage.removeItem('auth_token');
    window.location.href = '/login';
}

// مودال های اضافی
function editRequest(type, requestId) {
    alert('ویرایش درخواست - ابتدا پیاده‌سازی نشده');
}

function approveRequest(type, requestId) {
    alert('تأیید درخواست - ابتدا پیاده‌سازی نشده');
}

function rejectRequest(type, requestId) {
    alert('رد درخواست - ابتدا پیاده‌سازی نشده');
}