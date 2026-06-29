/**
 * سرویس Attendance System
 * 
 * استفاده:
 *   const attendance = new AttendanceService();
 *   attendance.checkIn().then(data => console.log(data));
 */

class AttendanceService {
    constructor() {
        // مسیر API را بر اساس موقعیت فعلی تعیین کن
        this.baseURL = this.getBaseURL();
        this.apiURL = this.baseURL + '/api';
        this.attendanceAPI = this.baseURL + '/attendance_system/api';
        this.token = localStorage.getItem('auth_token');
    }

    /**
     * تعیین BASE URL
     */
    getBaseURL() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        
        // اگر در attendance_system هستید
        if (window.location.pathname.includes('/attendance_system/')) {
            // به پوشه بالایی برو
            return `${protocol}//${host}`;
        }
        
        // پیش‌فرض
        return `${protocol}//${host}`;
    }

    /**
     * ثبت ورود
     */
    async checkIn() {
        try {
            const response = await fetch(
                `${this.attendanceAPI}/attendance/check-in.php`,
                {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                }
            );

            if (!response.ok) {
                if (response.status === 401) {
                    this.handleUnauthorized();
                    return { success: false, message: 'Unauthorized' };
                }
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Check-in successful:', data);
                this.showNotification('ورود با موفقیت ثبت شد', 'success');
            } else {
                console.error('❌ Check-in failed:', data.message);
                this.showNotification(data.message, 'error');
            }
            
            return data;

        } catch (error) {
            console.error('Error during check-in:', error);
            this.showNotification('خطا در ثبت ورود: ' + error.message, 'error');
            return { success: false, message: error.message };
        }
    }

    /**
     * ثبت خروج
     */
    async checkOut() {
        try {
            const response = await fetch(
                `${this.attendanceAPI}/attendance/check-out.php`,
                {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Check-out successful:', data);
                this.showNotification('خروج با موفقیت ثبت شد', 'success');
            }
            
            return data;

        } catch (error) {
            console.error('Error during check-out:', error);
            this.showNotification('خطا در ثبت خروج: ' + error.message, 'error');
            return { success: false, message: error.message };
        }
    }

    /**
     * دریافت وضعیت امروز
     */
    async getTodayStatus() {
        try {
            const response = await fetch(
                `${this.attendanceAPI}/attendance/today.php`,
                {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            console.error('Error getting today status:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * دریافت تقویم ماه
     */
    async getMonthlyCalendar(year, month) {
        try {
            const response = await fetch(
                `${this.attendanceAPI}/attendance/calendar.php?year=${year}&month=${month}`,
                {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            console.error('Error getting calendar:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * ایجاد درخواست جدید
     */
    async createRequest(requestType, startTime, endTime, description) {
        try {
            const response = await fetch(
                `${this.attendanceAPI}/requests/create.php`,
                {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        request_type: requestType,
                        start_time: startTime,
                        end_time: endTime,
                        description: description
                    })
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('درخواست با موفقیت ایجاد شد', 'success');
            }
            
            return data;

        } catch (error) {
            console.error('Error creating request:', error);
            this.showNotification('خطا در ایجاد درخواست', 'error');
            return { success: false, message: error.message };
        }
    }

    /**
     * دریافت اطلاعات کاربر
     */
    async getUserProfile() {
        try {
            const response = await fetch(
                `${this.apiURL}/auth/profile.php`,
                {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                if (response.status === 401) {
                    this.handleUnauthorized();
                }
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            console.error('Error getting profile:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * نمایش اطلاع‌رسانی
     */
    showNotification(message, type = 'info') {
        // ایجاد عنصر notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.top = '20px';
        notification.style.left = '20px';
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // حذف خودکار بعد از 5 ثانیه
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * مدیریت عدم احراز هویت
     */
    handleUnauthorized() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_info');
        window.location.href = '/index.php';
    }
}

/**
 * مثال استفاده:
 */

// اتصال DOM
document.addEventListener('DOMContentLoaded', function() {
    const attendance = new AttendanceService();

    // تست: نمایش اطلاعات
    attendance.getUserProfile().then(data => {
        if (data.success) {
            console.log('User:', data.user);
            document.getElementById('userName').textContent = 
                data.user.first_name + ' ' + data.user.last_name;
        }
    });

    // دریافت وضعیت امروز
    attendance.getTodayStatus().then(data => {
        if (data.success) {
            console.log('Today status:', data);
            // به‌روزرسانی UI
        }
    });

    // Event listeners
    document.getElementById('checkInBtn')?.addEventListener('click', () => {
        attendance.checkIn();
    });

    document.getElementById('checkOutBtn')?.addEventListener('click', () => {
        attendance.checkOut();
    });

    // دریافت تقویم برای ماه جاری
    const now = new Date();
    const shamsiYear = now.getFullYear(); // این را باید به تاریخ شمسی تبدیل کنید
    const shamsiMonth = now.getMonth() + 1;

    attendance.getMonthlyCalendar(shamsiYear, shamsiMonth).then(data => {
        if (data.success) {
            console.log('Calendar:', data);
            // render calendar
        }
    });
});
