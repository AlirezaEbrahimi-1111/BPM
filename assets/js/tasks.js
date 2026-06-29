
// بارگذاری اعلان‌ها
async function loadNotifications() {
    try {
        authToken = localStorage.getItem('auth_token');

        const response = await fetch('api/notifications/list.php', {
            headers: {
                'Authorization': 'Bearer ' + authToken
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // بروزرسانی badge تعداد
            document.getElementById('notificationCount').textContent = data.unread_count;
            
            // نمایش لیست
            renderNotifications(data.notifications);
        }
    } catch (error) {
        console.error('خطا در بارگذاری اعلان‌ها:', error);
    }
}

// نمایش لیست
function renderNotifications(notifications) {
    const container = document.getElementById('notificationList');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-3">اعلانی وجود ندارد</div>';
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        const icon = getNotificationIcon(notif.type);
        const bgClass = notif.is_read == 0 ? 'bg-light' : '';
        
        html += `
            <a href="${notif.link || '#'}" 
               class="dropdown-item ${bgClass}" 
               onclick="markAsRead(${notif.id})">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="bi bi-${icon} text-${notif.type}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <strong>${notif.title}</strong>
                        <div class="small text-muted">${notif.message}</div>
                        <div class="small text-muted mt-1">${formatTime(notif.created_at)}</div>
                    </div>
                </div>
            </a>
            <div class="dropdown-divider"></div>
        `;
    });
        
    container.innerHTML = html;
}

// آیکون بر اساس نوع
function getNotificationIcon(type) {
    const icons = {
        'info': 'info-circle',
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'danger': 'x-circle'
    };
    return icons[type] || 'bell';
}

// زمان نسبی (مثلاً: 5 دقیقه پیش)
function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // ثانیه
    
    if (diff < 60) return 'همین الان';
    if (diff < 3600) return Math.floor(diff / 60) + ' دقیقه پیش';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ساعت پیش';
    return Math.floor(diff / 86400) + ' روز پیش';
}

// علامت‌گذاری یکی خوانده شده
async function markAsRead(notifId) {
    await fetch('api/notifications/mark-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
        },
        body: JSON.stringify({ id: notifId })
    });
    
    loadNotifications(); // رفرش
}

// علامت‌گذاری همه خوانده شده
async function markAllAsRead() {
    await fetch('api/notifications/mark-all-read.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
        }
    });
    
    loadNotifications(); // رفرش
}

// بارگذاری اولیه
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    
    // بروزرسانی خودکار هر 30 ثانیه
    setInterval(loadNotifications, 30000);
});