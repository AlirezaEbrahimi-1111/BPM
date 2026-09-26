/**
 * QuickAdd — مینی‌مودال برای افزودن سریع مشتری / تأمین‌کننده / کالا از دل
 * فرم فاکتور، بدون رفتن به صفحه‌ی دیگر.
 *
 *   QuickAdd.customer(rec => { ... })   // rec = آبجکت کامل + id
 *   QuickAdd.supplier(rec => { ... })
 *   QuickAdd.product(rec  => { ... })
 *   QuickAdd.partner(rec  => { ... })
 *
 * وابستگی: bootstrap.bundle (برای Modal) + توکن در localStorage['auth_token'].
 */
const QuickAdd = (() => {
    const API = '/crm/api';

    function tok() {
        return localStorage.getItem('auth_token');
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;'
        } [c]));
    }

    function toEn(s) {
        return String(s == null ? '' : s)
            .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
            .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    }

    function _style() {
        if (document.getElementById('qa-style')) return;
        const s = document.createElement('style');
        s.id = 'qa-style';
        s.textContent = `
.qa-modal .modal-header{background:#8e57fe;color:#fff;border:0;border-radius:.4rem .4rem 0 0;padding:.8rem 1rem}
.qa-modal .modal-title{font-size:1rem;font-weight:700}
.qa-modal .btn-close{filter:invert(1) grayscale(1) brightness(2)}
.qa-modal label{font-size:.82rem;margin-bottom:.15rem}
.qa-modal .qa-err{font-size:.82rem}
`;
        document.head.appendChild(s);
    }

    async function post(path, body) {
        const r = await fetch(API + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            },
            body: JSON.stringify(body)
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
        return d;
    }

    // fields: [{name, label, type:'text'|'select'|'textarea'|'switch', options?, required?, col?}]
    function open(title, icon, fields, onSave) {
        _style();
        const rows = fields.map(f => {
            const col = f.col || 12;
            const req = f.required ? ' <span class="text-danger">*</span>' : '';
            let ctrl;
            if (f.type === 'select') {
                ctrl = `<select class="form-select form-select-sm" data-f="${f.name}">` +
                    f.options.map(o => `<option value="${esc(o.v)}">${esc(o.t)}</option>`).join('') + `</select>`;
            } else if (f.type === 'textarea') {
                ctrl = `<textarea class="form-control form-control-sm" rows="2" data-f="${f.name}"></textarea>`;
            } else if (f.type === 'switch') {
                return `<div class="col-12"><div class="form-check form-switch" style="display:flex;align-items:center;gap:8px;padding:0;margin:.2rem 0">
                    <input class="form-check-input" type="checkbox" data-f="${f.name}" style="margin:0;float:none">
                    <label class="form-check-label" style="order:-1;margin:0">${esc(f.label)}</label></div></div>`;
            } else {
                ctrl = `<input class="form-control form-control-sm" data-f="${f.name}" ${f.attr||''}>`;
            }
            return `<div class="col-md-${col}"><label>${esc(f.label)}${req}</label>${ctrl}</div>`;
        }).join('');

        const el = document.createElement('div');
        el.className = 'modal fade qa-modal';
        el.tabIndex = -1;
        el.innerHTML = `
<div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi ${icon} ms-2"></i>${esc(title)}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="qa-err text-danger mb-2" hidden></div>
      <div class="row g-2">${rows}</div>
    </div>
    <div class="modal-footer border-0 d-flex justify-content-end">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
      <button type="button" class="btn btn-primary px-5 qa-save">ذخیره</button>
    </div>
  </div>
</div>`;
        document.body.appendChild(el);
        const modal = new bootstrap.Modal(el);
        const errBox = el.querySelector('.qa-err');
        const val = name => {
            const c = el.querySelector(`[data-f="${name}"]`);
            if (!c) return '';
            return c.type === 'checkbox' ? c.checked : c.value.trim();
        };

        el.querySelector('.qa-save').addEventListener('click', async () => {
            errBox.hidden = true;
            const data = {};
            fields.forEach(f => data[f.name] = val(f.name));
            const missing = fields.filter(f => f.required && !String(data[f.name] || '').trim());
            if (missing.length) {
                errBox.textContent = 'این فیلدها الزامی‌اند: ' + missing.map(f => f.label).join('، ');
                errBox.hidden = false;
                return;
            }
            const btn = el.querySelector('.qa-save');
            btn.disabled = true;
            try {
                const rec = await onSave(data);
                modal.hide();
                if (window.showToast) showToast('افزوده شد', 'success');
            } catch (e) {
                errBox.textContent = e.message || 'خطا در ذخیره';
                errBox.hidden = false;
                btn.disabled = false;
            }
        });
        el.addEventListener('hidden.bs.modal', () => el.remove());
        modal.show();
        setTimeout(() => {
            const first = el.querySelector('input,select');
            if (first) first.focus();
        }, 200);
    }

    function customer(onDone) {
        open('مشتری سریع', 'bi-person-plus', [
            { name: 'type', label: 'نوع', type: 'select', col: 4, options: [{ v: 'legal', t: 'حقوقی (شرکت)' }, { v: 'individual', t: 'حقیقی (شخص)' }] },
            { name: 'name', label: 'نام', required: true, col: 8 },
            { name: 'national_id', label: 'شناسه / کد ملی', required: true, col: 6, attr: 'inputmode="numeric"' },
            { name: 'economic_code', label: 'کد اقتصادی', col: 6, attr: 'inputmode="numeric"' },
            { name: 'postal_code', label: 'کد پستی', required: true, col: 6, attr: 'inputmode="numeric"' },
            { name: 'phone', label: 'تلفن', col: 6, attr: 'inputmode="numeric"' },
            { name: 'address', label: 'آدرس', type: 'textarea' },
        ], async d => {
            const body = {
                type: d.type, name: d.name,
                national_id: toEn(d.national_id), economic_code: toEn(d.economic_code),
                postal_code: toEn(d.postal_code), phone: toEn(d.phone), address: d.address,
            };
            const res = await post('/customers', body);
            const rec = { id: res.id, mobile: '', city: '', province: '', ...body };
            onDone(rec);
            return rec;
        });
    }

    function supplier(onDone) {
        open('تأمین‌کننده سریع', 'bi-truck', [
            { name: 'name', label: 'نام', required: true },
            { name: 'phone', label: 'تلفن', col: 6, attr: 'inputmode="numeric"' },
            { name: 'mobile', label: 'موبایل', col: 6, attr: 'inputmode="numeric"' },
            { name: 'national_id', label: 'کد / شناسه ملی', col: 6, attr: 'inputmode="numeric"' },
            { name: 'economic_code', label: 'کد اقتصادی', col: 6, attr: 'inputmode="numeric"' },
            { name: 'address', label: 'آدرس', type: 'textarea' },
        ], async d => {
            const body = {
                name: d.name, phone: toEn(d.phone), mobile: toEn(d.mobile),
                national_id: toEn(d.national_id), economic_code: toEn(d.economic_code), address: d.address, note: '',
            };
            const res = await post('/inv/suppliers', body);
            const rec = { id: res.id, ...body };
            onDone(rec);
            return rec;
        });
    }

    function product(onDone) {
        open('کالای سریع', 'bi-box-seam', [
            { name: 'code', label: 'کد کالا', required: true, col: 4, attr: 'inputmode="numeric"' },
            { name: 'name', label: 'نام کالا', required: true, col: 8 },
            { name: 'unit', label: 'واحد', col: 4, attr: 'placeholder="عدد"' },
            { name: 'unit_price', label: 'قیمت واحد (ریال)', required: true, col: 4, attr: 'inputmode="numeric"' },
            { name: 'opening_stock', label: 'موجودی اولیه', col: 4, attr: 'inputmode="decimal"' },
            { name: 'is_tax_exempt', label: 'معاف از مالیات', type: 'switch' },
            { name: 'is_service', label: 'خدمت است (نه کالای فیزیکی)', type: 'switch' },
        ], async d => {
            const price = parseInt(toEn(d.unit_price).replace(/[^\d-]/g, ''), 10) || 0;
            if (price <= 0) throw new Error('قیمت واحد باید بزرگ‌تر از صفر باشد');
            const op = parseFloat(toEn(d.opening_stock).replace(/[^\d.-]/g, ''));
            const body = {
                code: toEn(d.code), name: d.name, unit: d.unit || 'عدد',
                unit_price: price, is_tax_exempt: !!d.is_tax_exempt, is_service: !!d.is_service,
            };
            if (!isNaN(op) && op) body.opening_stock = op;
            const res = await post('/inv/products', body);
            const rec = {
                id: res.id, code: body.code, name: body.name, unit: body.unit,
                unit_price: body.unit_price, is_tax_exempt: body.is_tax_exempt, is_service: body.is_service,
                stock: (!isNaN(op) && op) ? op : 0, reserved: 0, available: (!isNaN(op) && op) ? op : 0,
            };
            onDone(rec);
            return rec;
        });
    }

    function partner(onDone) {
        open('همکار سریع', 'bi-people', [
            { name: 'name', label: 'نام', required: true },
            { name: 'phone', label: 'تلفن (اختیاری)', attr: 'inputmode="numeric"' },
        ], async d => {
            const body = { name: d.name, phone: toEn(d.phone) };
            const res = await post('/inv/partners', body);
            const rec = { id: res.id, name: body.name, phone: body.phone, is_active: true };
            onDone(rec);
            return rec;
        });
    }

    return { customer, supplier, product, partner };
})();
