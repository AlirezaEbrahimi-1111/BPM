// ============================================
// 🎯 بهبود کارهای واگذار شده - نسخه تصحیح شده
// ============================================

// ============================================
// 🔍 جستجو در کارهای واگذار شده
// ============================================
function searchDelegatedTasks(query) {
    const searchTerm = query.toLowerCase().trim();
    
    let filteredTasks = delegatedTasksData;
    
    if (searchTerm) {
        filteredTasks = delegatedTasksData.filter(task => 
            task.title.toLowerCase().includes(searchTerm) ||
            (task.description && task.description.toLowerCase().includes(searchTerm)) ||
            (task.assignee_name && task.assignee_name.toLowerCase().includes(searchTerm))
        );
    }
    
    renderDelegatedTasks(filteredTasks);
}

// ============================================
// ⏱️ Countdown برای کارهای واگذار شده
// ============================================
function calculateDelegatedCountdown(dueDate) {
    if (!dueDate) return null;
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const due = new Date(dueDate);
    due.setHours(0, 0, 0, 0);
    
    const diffTime = due - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return { text: 'امروز', class: 'badge-info' };
    if (diffDays === 1) return { text: '1 روز مانده', class: 'badge-warning' };
    if (diffDays > 1 && diffDays <= 3) return { text: `${diffDays} روز مانده`, class: 'badge-warning' };
    if (diffDays < 0) return { text: `${Math.abs(diffDays)} روز تاخیر`, class: 'badge-danger' };
    if (diffDays > 3 && diffDays <= 7) return { text: `${diffDays} روز مانده`, class: 'badge-success' };
    return { text: `${diffDays} روز مانده`, class: 'badge-secondary' };
}

// ============================================
// 📊 Progress برای کارهای واگذار شده
// ============================================
function calculateDelegatedProgress() {
    if (!delegatedTasksData || delegatedTasksData.length === 0) return 0;
    
    const completed = delegatedTasksData.filter(t => t.status === 'completed').length;
    return Math.round((completed / delegatedTasksData.length) * 100);
}

// ============================================
// 🎨 نمایش بهتر کارهای واگذار شده - نسخه بهبود یافته
// ============================================
function renderDelegatedTasks(tasks) {
    const container = document.getElementById('delegatedTasksList');

    if (tasks.length === 0) {
        container.innerHTML = `
            <div class="empty-state-improved">
                <div class="empty-icon">
                    <i class="bi bi-arrow-right-circle"></i>
                </div>
                <h5 class="mt-3">هیچ کاری واگذار نکرده‌اید! 🎯</h5>
                <p class="text-muted">کارهای جدید ایجاد کنید و به تیمتان واگذار کنید</p>
                <button class="btn btn-primary mt-3" onclick="showNewTaskModal()">
                    <i class="bi bi-plus-circle me-2"></i>ایجاد کار جدید
                </button>
            </div>
        `;
        return;
    }

    let html = '';
    tasks.slice(0, 20).forEach(task => {
        const taskClass = getTaskClass(task);
        const priorityBadge = getPriorityBadge(task.priority);
        const statusBadge = getStatusBadge(task);
        
        // نوع کار
        const taskTypeIcon = task.task_type === 'continuous' 
            ? '🔄' 
            : task.task_type === 'periodic' 
            ? '📅' 
            : '⚙️';
        
        // Countdown
        const countdown = calculateDelegatedCountdown(task.due_date);
        const countdownBadge = countdown 
            ? `<span class="badge ${countdown.class}">${countdown.text}</span>` 
            : '';
        
        // شماره دوره معوقه
        const continuousBadge = task.task_type === 'continuous' && task.overdue_periods > 0
            ? `<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${task.overdue_periods} دوره معوقه</span>`
            : '';
        
        // نام مسئول انجام
        const assigneeName = task.assignee_name || 'نامشخص';

        html += `
            <div class="task-item ${taskClass}" onclick="viewTask(${task.id})">
                <!-- دکمه یادآوری - سمت چپ (خارج از کادر) -->
                <button class="btn btn-sm btn-remind" 
                    onclick="event.stopPropagation(); sendReminder(${task.id}, '${task.title}', '${assigneeName}')" 
                    title="ارسال یادآوری">
                    <i class="bi bi-bell"></i>
                </button>
                
                <!-- محتوای کار -->
                <div class="task-header">
                    <div class="task-title-section">
                        <div class="task-title">
                            <span class="task-type-icon" title="${task.task_type}">${taskTypeIcon}</span>
                            ${task.title}
                        </div>
                        <div class="task-assignee">
                            <i class="bi bi-person-circle me-1"></i>
                            واگذار به: <strong>${assigneeName}</strong>
                        </div>
                    </div>
                </div>
                
                <!-- Badges (Priority, Status, Countdown, Overdue) -->
                <div class="task-badges">
                    ${priorityBadge}
                    ${statusBadge}
                    ${continuousBadge}
                    ${countdownBadge}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// ============================================
// 🎛️ اضافه کردن جستجو برای کارهای واگذار
// ============================================
function addSearchBoxForDelegated() {
    const delegatedCard = document.querySelector('[id="delegatedTasksList"]').closest('.card');
    
    if (!delegatedCard) {
        console.warn('Delegated card not found');
        return;
    }
    
    if (!document.getElementById('delegatedTaskSearchBox')) {
        const searchHTML = `
            <div class="input-group mb-3" id="delegatedTaskSearchBox">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control border-start-0" 
                    placeholder="جستجو در کارهای واگذار شده..." 
                    onkeyup="searchDelegatedTasks(this.value)">
            </div>
        `;
        
        const delegatedBody = delegatedCard.querySelector('.card-body');
        delegatedBody.insertAdjacentHTML('afterbegin', searchHTML);
    }
}

// ============================================
// 📊 آپدیت Progress برای کارهای واگذار
// ============================================
function updateDelegatedProgress() {
    const progressPercent = calculateDelegatedProgress();
    
    // اگر کارت Progress واگذار موجود است
    const delegatedProgressCard = document.getElementById('delegatedProgressCard');
    if (delegatedProgressCard) {
        delegatedProgressCard.innerHTML = `
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                <h3 class="stats-number">${progressPercent}%</h3>
                <p class="stats-label">تکمیل شده</p>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar" style="width: ${progressPercent}%;"></div>
                </div>
                <i class="bi bi-percent position-absolute"
                    style="font-size: 3rem; opacity: 0.2; top: 10px; left: 15px;"></i>
            </div>
        `;
    }
}

// ============================================
// 🎨 CSS برای کارهای واگذار شده
// ============================================
const delegatedStyles = `
/* Search Box برای واگذار */
#delegatedTaskSearchBox {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

#delegatedTaskSearchBox .form-control {
    border: 1px solid #e8eaed;
    padding: 10px 14px;
    font-size: 14px;
}

#delegatedTaskSearchBox .form-control:focus {
    border-color: #f5576c;
    box-shadow: none;
}

/* Task Item برای واگذار - فضا برای دکمه سمت چپ */
.task-item {
    position: relative;
    overflow: visible;
    padding-left: 45px;
}

/* دکمه یادآوری - موقعیت صحیح */
.btn-remind {
    position: absolute !important;
    left: -20px !important;      /* خارج از کادر، سمت چپ */
    top: 50% !important;          /* وسط عمودی */
    transform: translateY(-50%) !important;
    
    /* استایل دکمه */
    background: linear-gradient(135deg, #ffd700, #ffaa00) !important;
    color: white !important;
    border: none !important;
    border-radius: 50% !important;
    width: 36px !important;
    height: 36px !important;
    padding: 0 !important;
    
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 15px rgba(255, 170, 0, 0.4) !important;
    z-index: 100 !important;
    opacity: 0 !important;
}

/* نمایش دکمه با hover روی task-item */
.task-item:hover .btn-remind {
    opacity: 1 !important;
}

/* hover روی خود دکمه */
.btn-remind:hover {
    background: linear-gradient(135deg, #ffaa00, #ff8c00) !important;
    transform: translateY(-50%) scale(1.15) !important;
    box-shadow: 0 6px 25px rgba(255, 170, 0, 0.6) !important;
}

/* active state */
.btn-remind:active {
    transform: translateY(-50%) scale(1.05) !important;
}

/* آیکون bell */
.btn-remind i {
    font-size: 1.1rem !important;
    animation: bell-ring 2s infinite;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

/* انیمیشن bell */
@keyframes bell-ring {
    0% { transform: rotate(0); }
    10% { transform: rotate(15deg); }
    20% { transform: rotate(-15deg); }
    30% { transform: rotate(15deg); }
    40% { transform: rotate(-15deg); }
    50% { transform: rotate(0); }
    100% { transform: rotate(0); }
}

/* برای موبایل */
@media (max-width: 768px) {
    .task-item {
        padding-left: 40px;
    }
    
    .btn-remind {
        left: -18px !important;
        width: 32px !important;
        height: 32px !important;
    }
    
    .btn-remind i {
        font-size: 1rem !important;
    }
}
`;

// ============================================
// 🚀 راه‌اندازی بهبودهای کارهای واگذار
// ============================================
function initializeDelegatedImprovements() {
    // اضافه کردن Styles (اگر قبلا اضافه نشده باشد)
    if (!document.getElementById('delegatedStyles')) {
        const styleTag = document.createElement('style');
        styleTag.id = 'delegatedStyles';
        styleTag.innerHTML = delegatedStyles;
        document.head.appendChild(styleTag);
        console.log('✅ CSS کارهای واگذار اعمال شد');
    }
    
    // اضافه کردن جستجو
    addSearchBoxForDelegated();
    
    console.log('✅ بهبودهای کارهای واگذار فعال شد');
}

// ============================================
// ✅ راه‌اندازی هنگام بارگذاری کارهای واگذار
// ============================================
// این تابع باید در loadDelegatedTasks() فراخوانی شود
// بعد از renderDelegatedTasks(delegatedTasksData);
// اضافه کنید:
// initializeDelegatedImprovements();