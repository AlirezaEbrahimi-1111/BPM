/**
 * sections-helper.js
 * ─────────────────────────────────────────────
 * لود بخش‌های فعالیت از دیتابیس و ساخت map فارسی
 * 
 * استفاده:
 *   1. در HTML:  <script src="assets/js/sections-helper.js"></script>
 *   2. در کد:    await loadSectionMap();
 *   3. تبدیل:    getSectionLabel('sales')  → 'فروش'
 * ─────────────────────────────────────────────
 */

// ── متغیرهای global ──
let sectionMap = {};         // { 'sales': 'فروش', 'technical': 'فنی', ... }
let sectionList = [];        // [{ section_key, section_label }, ...]
let _sectionsLoaded = false; // جلوگیری از لود تکراری

/**
 * لود بخش‌ها از API و ساخت map
 * اگه قبلاً لود شده، دوباره لود نمی‌کنه (مگه force=true)
 */
async function loadSectionMap(force = false) {
    if (_sectionsLoaded && !force) return sectionMap;

    try {
        const token = localStorage.getItem('auth_token');
        const res = await fetch('/api/organization/activity-sections.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await res.json();

        if (data.success && data.sections) {
            sectionList = data.sections;
            sectionMap = {};

            // مقدار ثابت "مدیریت" همیشه هست
            sectionMap['management'] = 'مدیریت';

            // بقیه از دیتابیس
            sectionList.forEach(s => {
                sectionMap[s.section_key] = s.section_label;
            });

            _sectionsLoaded = true;
        }
    } catch (e) {
        console.error('خطا در لود بخش‌های فعالیت:', e);
    }

    return sectionMap;
}

/**
 * برگرداندن نام فارسی یک بخش
 * اگه پیدا نشد، خود مقدار انگلیسی رو برمی‌گردونه
 */
function getSectionLabel(key) {
    if (!key) return '—';
    return sectionMap[key] || key;
}

/**
 * ساخت option های select از بخش‌ها
 * selectedValue: مقدار انتخاب‌شده فعلی
 * includeEmpty: آیا گزینه "انتخاب کنید" اضافه بشه
 * includeManagement: آیا "مدیریت" اضافه بشه
 */
function buildSectionSelectOptions(selectedValue = '', includeEmpty = true, includeManagement = true) {
    let html = '';
    if (includeEmpty) {
        html += '<option value="">انتخاب کنید...</option>';
    }
    if (includeManagement) {
        html += `<option value="management" ${selectedValue === 'management' ? 'selected' : ''}>مدیریت</option>`;
    }
    sectionList.forEach(s => {
        html += `<option value="${s.section_key}" ${selectedValue === s.section_key ? 'selected' : ''}>${s.section_label}</option>`;
    });
    return html;
}

/**
 * پر کردن یک select element با بخش‌ها
 * selectId: آیدی select element
 * selectedValue: مقدار انتخاب‌شده
 */
function fillSectionSelect(selectId, selectedValue = '', includeManagement = true) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = buildSectionSelectOptions(selectedValue, true, includeManagement);
}

/**
 * ساخت option های فیلتر (با "همه بخش‌ها")
 */
function fillSectionFilter(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">همه بخش‌ها</option>';
    sel.innerHTML += `<option value="management">مدیریت</option>`;
    sectionList.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.section_key;
        opt.textContent = s.section_label;
        sel.appendChild(opt);
    });
}
