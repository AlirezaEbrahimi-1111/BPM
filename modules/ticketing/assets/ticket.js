/* ticket.js — رفتارهای فرم تیکت */

document.addEventListener('DOMContentLoaded', function () {

  /* ── نمایش نام فایل‌های انتخاب‌شده ── */
  const fileInput = document.getElementById('attachments');
  const preview  = document.getElementById('selectedFiles');

  if (fileInput && preview) {
    fileInput.addEventListener('change', function () {
      preview.innerHTML = '';
      Array.from(this.files).forEach(function (file) {
        const tag = document.createElement('span');
        tag.className = 'selected-file-tag';
        tag.innerHTML =
          '<i class="bi bi-paperclip"></i>' +
          truncate(file.name, 28);
        preview.appendChild(tag);
      });
    });
  }

  /* ── ارسال فرم با نشانه‌ی loading ── */
  const form   = document.getElementById('replyForm');
  const submit = form && form.querySelector('[type="submit"]');

  if (form && submit) {
    form.addEventListener('submit', function () {
      submit.disabled = true;
      submit.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ارسال...';
    });
  }

  /* ── scroll به پایین مکالمه بعد از لود ── */
  const conv = document.querySelector('.conversation-wrapper');
  if (conv) {
    conv.scrollTop = conv.scrollHeight;
  }

});

function truncate(str, max) {
  return str.length > max ? str.substring(0, max) + '…' : str;
}