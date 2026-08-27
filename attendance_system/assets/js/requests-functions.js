// ============================================
// توابع کمکی برای سیستم درخواست‌ها
// ============================================

/**
 * ✅ تابع تعیین وضعیت کامل درخواست با نام تأییدکننده
 */
function getFullStatus(request) {
    if (request.status === 'approved') return 'تأیید شده';
    if (request.status === 'rejected') return 'رد شده';
    
    // برای مرخصی
    if (request.type === 'leave') {
        if (request.substitute_approval === 'pending') 
            return 'در انتظار تأیید جانشین';
        if (request.manager_approval === 'pending') 
            return 'در انتظار تأیید مدیر';
        if (request.supervisor_approval === 'pending') 
            return 'در انتظار تأیید مسئول';
    }
    
    // برای مأموریت، فراموشی
    if (request.type === 'mission' || request.type === 'forget') {
        if (request.manager_approval === 'pending') 
            return 'در انتظار تأیید مدیر';
        if (request.supervisor_approval === 'pending') 
            return 'در انتظار تأیید مسئول';
    }
    
    // برای مشکل فنی
    if (request.type === 'technical') {
        if (request.status === 'pending')
            return 'در انتظار تأیید مسئول';
    }
    
    return 'در انتظار';
}

/**
 * ✅ تابع دریافت دلیل رد
 */
function getRejectNotes(request) {
    if (!request) return null;
    
    if (request.type === 'leave') {
        if (request.substitute_approval === 'rejected') return request.substitute_notes;
        if (request.manager_approval === 'rejected') return request.manager_notes;
        if (request.supervisor_approval === 'rejected') return request.supervisor_notes;
    }
    else if (request.type === 'mission' || request.type === 'forget') {
        if (request.manager_approval === 'rejected') return request.manager_notes;
        if (request.supervisor_approval === 'rejected') return request.supervisor_notes;
    }
    else if (request.type === 'technical') {
        if (request.status === 'rejected') return request.admin_notes;
    }
    
    return null;
}

/**
 * ✅ نمایش popup برای دلیل رد
 */
function showRejectReason(notes) {
    if (!notes) {
        alert('دلیل رد ثبت نشده است');
        return;
    }
    
    // می‌تونید از یک modal زیباتر استفاده کنید
    alert('دلیل رد:\n\n' + notes);
}

/**
 * ✅ ساخت HTML برای نمایش وضعیت با آیکون رد
 */
function getStatusHTML(request) {
    let statusHTML = '';
    
    if (request.status === 'rejected') {
        const rejectNotes = getRejectNotes(request);
        statusHTML = `
            <span class="badge bg-danger">رد شده</span>
            ${rejectNotes ? `<span class="ms-1 reject-icon" style="cursor:pointer" 
                onclick="showRejectReason(\`${rejectNotes.replace(/`/g, '\\`')}\`)">💬</span>` : ''}
        `;
    } else if (request.status === 'approved') {
        statusHTML = `<span class="badge bg-success">تأیید شده</span>`;
    } else {
        const fullStatus = getFullStatus(request);
        statusHTML = `<span class="badge bg-warning text-dark">${fullStatus}</span>`;
    }
    
    return statusHTML;
}

/**
 * ✅ تابع تأیید یا رد درخواست
 */
async function approveRequest(id, type, action) {
    const notes = prompt(
        action === 'approve' 
            ? 'یادداشت (اختیاری):' 
            : 'دلیل رد (الزامی):'
    );
    
    if (action === 'reject' && !notes) {
        alert('لطفاً دلیل رد را وارد کنید');
        return;
    }
    
    try {
        const response = await fetch('/attendance_system/api/requests/approve.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, type, action, notes})
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            // ✅ رفرش لیست منتظر تأیید بدون خروج از صفحه
            if (typeof loadPendingApprovals === 'function') {
                loadPendingApprovals();
            }
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('خطا:', error);
        alert('خطا در ارسال درخواست');
    }
}

/**
 * ✅ ساخت HTML برای timeline تأییدات
 */
function getTimelineHTML(timeline) {
    if (!timeline || timeline.length === 0) {
        return '<p class="text-muted">تاکنون تأییدی ثبت نشده است</p>';
    }
    
    let html = '<div class="approval-timeline">';
    
    timeline.forEach((item, index) => {
        const isApproved = item.status === 'approved';
        const markerClass = isApproved ? 'bg-success' : 'bg-danger';
        const badgeClass = isApproved ? 'bg-success' : 'bg-danger';
        const statusText = isApproved ? 'تأیید' : 'رد';
        
        // فرمت تاریخ و ساعت
        let dateTime = '';
        if (item.date) {
            if (window.TimeSync) {
                const dd = TimeSync.formatJalali(item.date), tt = TimeSync.formatTimeOnly(item.date);
                dateTime = dd ? `${dd} - ${tt}` : '';
            } else {
                const d = new Date(item.date);
                const time = d.toLocaleTimeString('fa-IR', {hour: '2-digit', minute: '2-digit'});
                const date = d.toLocaleDateString('fa-IR');
                dateTime = `${date} - ${time}`;
            }
        }
        
        html += `
            <div class="timeline-item">
                <div class="timeline-marker ${markerClass}"></div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${item.role}</strong>
                            ${item.approver_name ? `<span class="text-muted"> - ${item.approver_name}</span>` : ''}
                        </div>
                        <span class="badge ${badgeClass}">${statusText}</span>
                    </div>
                    ${dateTime ? `<small class="text-muted d-block mt-1">${dateTime}</small>` : ''}
                    ${item.notes ? `<p class="mb-0 mt-2"><small class="text-muted">${item.notes}</small></p>` : ''}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // اضافه کردن CSS
    html += `
        <style>
            .approval-timeline {
                position: relative;
                padding: 20px 0;
            }
            .timeline-item {
                display: flex;
                margin-bottom: 20px;
                position: relative;
            }
            .timeline-item:not(:last-child)::after {
                content: '';
                position: absolute;
                right: 5px;
                top: 25px;
                width: 2px;
                height: calc(100% + 10px);
                background: #e2e8f0;
            }
            .timeline-marker {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-top: 4px;
                margin-left: 15px;
                flex-shrink: 0;
                position: relative;
                z-index: 1;
            }
            .timeline-content {
                flex: 1;
                background: #f8fafc;
                padding: 12px;
                border-radius: 8px;
            }
            .reject-icon {
                display: inline-block;
                font-size: 16px;
                transition: transform 0.2s;
            }
            .reject-icon:hover {
                transform: scale(1.2);
            }
        </style>
    `;
    
    return html;
}

/**
 * ✅ بارگذاری درخواست‌های منتظر تأیید (با رفرش بدون خروج از صفحه)
 */
async function loadPendingApprovals() {
    const container = document.getElementById('pending-approvals-container');
    if (!container) return;
    
    try {
        const response = await fetch('/attendance_system/api/requests/pending-approvals.php');
        const result = await response.json();
        
        if (!result.success) {
            container.innerHTML = '<p class="text-danger">خطا در دریافت اطلاعات</p>';
            return;
        }
        
        if (!result.data || result.data.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">درخواستی در انتظار تأیید شما نیست</p>';
            return;
        }
        
        let html = '<div class="table-responsive"><table class="table table-hover">';
        html += `
            <thead>
                <tr>
                    <th>کد</th>
                    <th>درخواست‌دهنده</th>
                    <th>نوع</th>
                    <th>تاریخ</th>
                    <th>توضیحات</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
        `;
        
        const typeLabels = {
            'mission': 'مأموریت',
            'leave': 'مرخصی',
            'forget': 'فراموشی',
            'technical': 'مشکل فنی'
        };
        
        result.data.forEach(req => {
            const requesterName = `${req.first_name || ''} ${req.last_name || ''}`.trim();
            const typeLabel = typeLabels[req.type] || req.type;
            
            html += `
                <tr>
                    <td>${req.request_code || '-'}</td>
                    <td>${requesterName}</td>
                    <td>${typeLabel}</td>
                    <td>${req.start_datetime || '-'}</td>
                    <td>${req.description || '-'}</td>
                    <td>${getStatusHTML(req)}</td>
                    <td>
                        <button class="btn btn-sm btn-success me-1" 
                            onclick="approveRequest(${req.id}, '${req.type}', 'approve')">
                            تأیید
                        </button>
                        <button class="btn btn-sm btn-danger" 
                            onclick="approveRequest(${req.id}, '${req.type}', 'reject')">
                            رد
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('خطا در بارگذاری درخواست‌ها:', error);
        container.innerHTML = '<p class="text-danger">خطا در دریافت اطلاعات</p>';
    }
}

// ✅ بارگذاری خودکار هنگام لود صفحه
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('pending-approvals-container')) {
        loadPendingApprovals();
    }
});