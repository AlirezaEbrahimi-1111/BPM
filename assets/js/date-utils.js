// ابزار تاریخ محلی — جایگزین امن toISOString برای «تاریخ تقویمی»
// چرا؟ toISOString زمان را به UTC می‌برد و در ایران یک روز عقب می‌اندازد.
function toLocalYMD(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}
function todayLocal() {
    return toLocalYMD(new Date());
}