#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
#  نصبِ باینریِ تازه‌ی crm-service که CI در incoming/ گذاشته + ری‌استارتِ سرویس.
#
#  این فایل را فقط root می‌تواند ویرایش کند. کاربرِ `deploy` (که GitHub Actions
#  با آن SSH می‌زند) از طریقِ یک خطِ /etc/sudoers.d/bmp-crm بدونِ رمز صدایش می‌زند:
#      deploy ALL=(root) NOPASSWD: /opt/bmp-crm/apply-crm-binary.sh
#
#  نصبِ یک‌باره:
#      sudo install -o root -g root -m 0755 apply-crm-binary.sh /opt/bmp-crm/apply-crm-binary.sh
#      sudo install -o deploy -g deploy -d /opt/bmp-crm/incoming
#      sudo install -m 0440 sudoers-bmp-crm /etc/sudoers.d/bmp-crm && sudo visudo -c
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SRC=/opt/bmp-crm/incoming/crm-service-linux
DEST=/opt/bmp-crm/crm-service

if [ ! -f "$SRC" ]; then
    echo "apply-crm-binary: $SRC یافت نشد — چیزی برای نصب نیست" >&2
    exit 1
fi

# کپی + مالکیت + مجوزِ اجرا، همه با هم.
install -o www-data -g www-data -m 0755 "$SRC" "$DEST"
rm -f "$SRC"

systemctl restart bmp-crm
sleep 1
systemctl is-active bmp-crm
echo "apply-crm-binary: crm-service به‌روزرسانی و ری‌استارت شد"
