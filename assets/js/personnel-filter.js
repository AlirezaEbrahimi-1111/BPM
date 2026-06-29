/**
 * ================================================
 * 🔧 فیلتر پرسنل - نسخه اصلاح شده
 * ================================================
 */

// HTML فیلتر پرسنل (نسخه بهبود شده)
const personnelFilterHTML = `
<div class="dropdown-wrapper" style="flex-shrink: 0;">
    <button class="action-btn" onclick="toggleDropdown('personnelDropdown')" style="gap: 0.4rem; padding: 0.65rem 0.9rem;">
        <i class="bi bi-people"></i> <span style="font-size: 0.8rem;">پرسنل</span>
    </button>
    <div id="personnelDropdown" class="dropdown-menu-custom" style="min-width: 220px; width: 220px; max-height: 450px; overflow-y: auto; right: auto; left: 0;">
        <div class="accordion-item-header" style="padding: 0.8rem 1rem; font-weight: 700; background: rgba(124, 58, 237, 0.05); border-bottom: 2px solid rgba(124, 58, 237, 0.15); font-size: 0.8rem;">
            <i class="bi bi-funnel"></i> انتخاب پرسنل
        </div>
        <div id="personnelList" style="padding: 0.4rem;">
            <!-- فهرست پرسنل اینجا بارگذاری می‌شود -->
        </div>
        <div style="padding: 0.6rem 0.8rem; border-top: 1px solid var(--border-light); text-align: center;">
            <button class="submenu-item" style="width: 100%; text-align: center; border: none; padding: 0.5rem; font-size: 0.75rem;" onclick="clearPersonnelFilter()">
                <i class="bi bi-arrow-counterclockwise"></i> حذف
            </button>
        </div>
    </div>
</div>
`;

// ================================================
// متغیرهای فیلتر پرسنل
// ================================================

let selectedPersonnel = null;
let personnelTaskCounts = {};

// ================================================
// تابع: بارگذاری و نمایش لیست پرسنل
// ================================================

function loadAndDisplayPersonnel() {
    personnelTaskCounts = {};
    
    allTasks.forEach(task => {
        if (task.creator_id === currentUser.id && task.assignee_id !== currentUser.id) {
            const assigneeId = task.assignee_id;
            if (!personnelTaskCounts[assigneeId]) {
                personnelTaskCounts[assigneeId] = {
                    count: 0,
                    name: task.assignee_name
                };
            }
            personnelTaskCounts[assigneeId].count++;
        }
    });

    const personnelList = document.getElementById('personnelList');
    
    if (Object.keys(personnelTaskCounts).length === 0) {
        personnelList.innerHTML = `
            <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                <i class="bi bi-inbox" style="font-size: 1.5rem; opacity: 0.5; margin-bottom: 0.25rem; display: block;"></i>
                <p style="margin: 0.25rem 0 0 0;">بدون کار ارجاع شده</p>
            </div>
        `;
        return;
    }

    let html = '';
    Object.entries(personnelTaskCounts).forEach(([id, data]) => {
        const isSelected = selectedPersonnel == id;
        html += `
            <div class="submenu-item personnel-item" 
                 data-id="${id}"
                 onclick="filterByPersonnel(${id}, '${data.name}')"
                 style="display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 0.9rem; cursor: pointer; transition: all 0.2s ease; font-size: 0.85rem; ${isSelected ? 'background: rgba(124, 58, 237, 0.15); color: var(--primary); border-right: 3px solid var(--primary);' : 'border-right: 3px solid transparent;'} margin-bottom: 0.2rem; border-radius: 0;">
                <div style="display: flex; align-items: center; gap: 0.4rem; flex: 1; min-width: 0;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #5b21b6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;">
                        ${data.name.charAt(0)}
                    </div>
                    <span style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${data.name}
                    </span>
                </div>
                <span style="background: ${isSelected ? 'var(--primary)' : '#e2e8f0'}; color: ${isSelected ? 'white' : 'var(--text-dark)'}; padding: 0.25rem 0.6rem; border-radius: 10px; font-size: 0.7rem; font-weight: 700; flex-shrink: 0; margin-right: 0.4rem;">
                    ${enTofaNumber(data.count)}
                </span>
            </div>
        `;
    });

    personnelList.innerHTML = html;
}

// ================================================
// تابع: فیلتر کردن بر اساس پرسنل
// ================================================

function filterByPersonnel(personnelId, personnelName) {
    selectedPersonnel = personnelId;
    
    closeAllDropdowns();
    
    const btn = document.querySelector('button[onclick*="toggleDropdown(\'personnelDropdown\')"]');
    if (btn) {
        btn.innerHTML = `<i class="bi bi-funnel-fill"></i> <span style="font-size: 0.8rem;">${personnelName.split(' ')[0]}</span>`;
        btn.style.borderColor = 'var(--primary)';
        btn.style.color = 'var(--primary)';
    }

    const filtered = allTasks.filter(task => {
        return task.creator_id === currentUser.id && task.assignee_id == personnelId;
    });

    displayTasks(filtered);
    loadAndDisplayPersonnel();
}

// ================================================
// تابع: حذف فیلتر پرسنل
// ================================================

function clearPersonnelFilter() {
    selectedPersonnel = null;
    
    const btn = document.querySelector('button[onclick*="toggleDropdown(\'personnelDropdown\')"]');
    if (btn) {
        btn.innerHTML = `<i class="bi bi-people"></i> <span style="font-size: 0.8rem;">پرسنل</span>`;
        btn.style.borderColor = '';
        btn.style.color = '';
    }

    const filtered = allTasks.filter(task => {
        return task.creator_id === currentUser.id && task.assignee_id !== currentUser.id;
    });

    displayTasks(filtered);
    loadAndDisplayPersonnel();
    closeAllDropdowns();
}

// ================================================
// CSS اضافی - بهبود شده
// ================================================

const personnelFilterStyles = `
    #personnelList {
        max-height: 380px;
        overflow-y: auto;
        padding: 0.3rem !important;
    }

    #personnelList::-webkit-scrollbar {
        width: 5px;
    }

    #personnelList::-webkit-scrollbar-track {
        background: transparent;
    }

    #personnelList::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 5px;
    }

    #personnelList::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .personnel-item {
        margin-bottom: 0.15rem !important;
        border-radius: 6px !important;
        border-right: 3px solid transparent !important;
    }

    .personnel-item:hover {
        background: rgba(124, 58, 237, 0.08) !important;
        color: var(--primary) !important;
        padding-left: 1rem !important;
    }

    .personnel-item span:last-child {
        transition: all 0.2s ease;
    }

    /* فاصله‌های تنظیم شده */
    .toolbar-actions {
        margin-right: 0.5rem !important;
        gap: 0.5rem !important;
    }

    .dropdown-wrapper {
        margin: 0 0.3rem !important;
    }
`;

function injectPersonnelFilterStyles() {
    const style = document.createElement('style');
    style.textContent = personnelFilterStyles;
    document.head.appendChild(style);
}

document.addEventListener('DOMContentLoaded', function() {
    injectPersonnelFilterStyles();
});