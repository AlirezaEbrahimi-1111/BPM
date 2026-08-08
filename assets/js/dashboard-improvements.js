// ============================================
// 🎯 بهبودهای بدون Sort/Filter
// ============================================

// ============================================
// 📊 تابع کمکی: محاسبه Progress Percentage
// ============================================
function calculateProgressPercentage() {
    if (!myTasksData || myTasksData.length === 0) return 0;
    
    const completed = myTasksData.filter(t => t.status === 'completed').length;
    return Math.round((completed / myTasksData.length) * 100);
}

// ============================================
// ⏱️ تابع کمکی: Countdown Timer
// ============================================
function calculateCountdown(dueDate) {
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
// 🔍 جستجو در کارها
// ============================================
function searchTasks(query) {
    const searchTerm = query.toLowerCase().trim();
    
    let filteredTasks = myTasksData;
    
    if (searchTerm) {
        filteredTasks = myTasksData.filter(task => 
            task.title.toLowerCase().includes(searchTerm) ||
            (task.description && task.description.toLowerCase().includes(searchTerm)) ||
            (task.creator_name && task.creator_name.toLowerCase().includes(searchTerm)) ||
            (task.assignee_name && task.assignee_name.toLowerCase().includes(searchTerm))
        );
    }
    
    // اعمال فیلتر فعلی
    const activeFilter = document.querySelector('.filter-btn.active');
    if (activeFilter) {
        const filterType = activeFilter.textContent.trim();
        if (filterType === 'امروز') {
            const today = new Date().toISOString().split('T')[0];
            filteredTasks = filteredTasks.filter(task => {
                const isRegular = task.task_type === 'periodic' && task.due_date === today;
                const isContinuous = task.task_type === 'continuous' && task.overdue_periods > 0;
                return isRegular || isContinuous;
            });
        } else if (filterType === 'عقب افتاده') {
            const today = new Date().toISOString().split('T')[0];
            filteredTasks = filteredTasks.filter(task => {
                const isRegular = task.task_type === 'periodic' && task.due_date < today;
                const isContinuous = task.task_type === 'continuous' && task.overdue_periods > 0;
                return isRegular || isContinuous;
            });
        }
    }
    
    renderMyTasks(filteredTasks);
}

// ============================================
// 🎨 نمایش بهتر کارهای من (رندر شده)
// ============================================
function renderMyTasks(tasks) {
    const container = document.getElementById('myTasksList');

    if (tasks.length === 0) {
        container.innerHTML = `
            <div class="empty-state-improved">
                <div class="empty-icon">
                    <i class="bi bi-clipboard2-check"></i>
                </div>
                <h5 class="mt-3">هیچ کاری برای امروز وجود ندارد! ✅</h5>
                <p class="text-muted">تمام کارهای امروز انجام شده است</p>
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
        const countdown = calculateCountdown(task.due_date);
        const countdownBadge = countdown
            ? `<span class="badge ${countdown.class}">${countdown.text}</span>`
            : '';

        // شماره دوره معوقه
        const continuousBadge = task.task_type === 'continuous' && task.overdue_periods > 0
            ? `<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${task.overdue_periods} دوره معوقه</span>`
            : '';

        html += `
            <div class="task-item ${taskClass}" onclick="viewTask(${task.id})">
                <div class="task-header">
                    <div class="task-title-section">
                        <div class="task-title">
                            <span class="task-type-icon" title="${task.task_type}">${taskTypeIcon}</span>
                            ${task.title}
                        </div>
                        <div class="task-assignee">
                            <i class="bi bi-person-circle me-1"></i>
                            ایجاد کننده: <strong>${task.creator_name || 'نامشخص'}</strong>
                        </div>
                    </div>
                    <div class="task-actions">
                        ${task.status === 'not_started' ? `
                            <button class="btn btn-sm btn-success" 
                                onclick="event.stopPropagation(); startTask(${task.id})" 
                                title="شروع کار">
                                <i class="bi bi-play-fill"></i>
                            </button>
                        ` : ''}
                        ${task.status === 'in_progress' ? `
                            <button class="btn btn-sm btn-primary" 
                                onclick="event.stopPropagation(); completeTask(${task.id})" 
                                title="تکمیل کار">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                
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
// 🎛️ UI: Search Box
// ============================================
function addSearchBox() {
    const filterButtonsDiv = document.querySelector('.filter-buttons');
    
    if (!document.getElementById('taskSearchBox')) {
        const searchHTML = `
            <div class="input-group mb-3" id="taskSearchBox">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control border-start-0" 
                    placeholder="جستجو در کارها..." 
                    onkeyup="searchTasks(this.value)">
            </div>
        `;
        
        filterButtonsDiv.insertAdjacentHTML('beforebegin', searchHTML);
    }
}

// ============================================
// 📊 نمایش Progress Percentage
// ============================================
function updateProgressStats() {
    const progressPercent = calculateProgressPercentage();
    
    // اگر کارت Progress موجود است، به‌روزرسانی کنید
    const progressCard = document.getElementById('progressCard');
    if (progressCard) {
        progressCard.innerHTML = `
            <div class="stats-card" style="background: linear-gradient(135deg, #667eea, #5568d3);">
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
// 🚀 راه‌اندازی بهبودهای جدید
// ============================================
function initializeImprovements() {
    // اضافه کردن Styles
    const styleTag = document.createElement('style');
    styleTag.innerHTML = improvementStyles;
    document.head.appendChild(styleTag);
    
    // اضافه کردن UI Elements
    addSearchBox();
    
    // به‌روزرسانی Stats
    updateProgressStats();
}

// ============================================
// 🔧 توابع گمشده در Dashboard
// ============================================

/**
 * نمایش کارهای من در داشبورد
 */


/**
 * محاسبه Countdown برای کارها
 */
function calculateCountdown(dueDate) {
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

/**
 * اضافه کردن جستجو برای کارهای من
 */
function addSearchBox() {
    const myTasksCard = document.querySelector('[id="myTasksList"]').closest('.card');

    if (!myTasksCard) {
        console.warn('My tasks card not found');
        return;
    }

    if (!document.getElementById('myTaskSearchBox')) {
        const searchHTML = `
            <div class="input-group mb-3" id="myTaskSearchBox">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control border-start-0" 
                    placeholder="جستجو در کارهای من..." 
                    onkeyup="searchMyTasks(this.value)">
            </div>
        `;

        const myTasksBody = myTasksCard.querySelector('.card-body');
        myTasksBody.insertAdjacentHTML('afterbegin', searchHTML);
    }
}

/**
 * جستجو در کارهای من
 */
function searchMyTasks(query) {
    const searchTerm = query.toLowerCase().trim();

    let filteredTasks = myTasksData;

    if (searchTerm) {
        filteredTasks = myTasksData.filter(task =>
            task.title.toLowerCase().includes(searchTerm) ||
            (task.description && task.description.toLowerCase().includes(searchTerm)) ||
            (task.creator_name && task.creator_name.toLowerCase().includes(searchTerm))
        );
    }

    renderMyTasks(filteredTasks);
}

/**
 * محاسبه درصد تکمیل
 */
function calculateProgressPercentage() {
    if (myTasksData.length === 0) return 0;

    const completedCount = myTasksData.filter(task =>
        task.status === 'completed' || task.status === 'approved'
    ).length;

    return Math.round((completedCount / myTasksData.length) * 100);
}

/**
 * شروع کار
 */
function startTask(taskId) {
    uiConfirm('آیا می‌خواهید این کار را شروع کنید؟', async function () {
        try {
            const response = await fetch('/api/tasks/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    task_id: taskId,
                    status: 'in_progress',
                    notes: 'کار شروع شد'
                })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('کار با موفقیت شروع شد', 'success');
                loadDashboardData();
            } else {
                showAlert(data.message || 'خطا در شروع کار', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('خطا در ارتباط با سرور', 'error');
        }
    });
}

/**
 * تکمیل کار
 */
function completeTask(taskId) {
    uiConfirm('آیا می‌خواهید این کار را تکمیل کنید؟', async function () {
        try {
            const response = await fetch('/api/tasks/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    task_id: taskId,
                    status: 'completed',
                    notes: 'کار تکمیل شد'
                })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('کار با موفقیت تکمیل شد', 'success');
                loadDashboardData();
            } else {
                showAlert(data.message || 'خطا در تکمیل کار', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('خطا در ارتباط با سرور', 'error');
        }
    });
}