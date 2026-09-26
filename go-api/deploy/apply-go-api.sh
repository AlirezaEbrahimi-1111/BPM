#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
#  نصب باینری تازه‌ی go-api که CI در incoming/ گذاشته + ری‌استارت سرویس.
#
#  این فایل root-owned است. کاربر `deploy` (که GitHub Actions با آن SSH می‌زند)
#  از طریق یک خط /etc/sudoers.d/bpm-go-api بدون رمز صدایش می‌زند.
#
#  نصب یک‌باره روی سرور:
#      sudo install -o root -g root -m 0755 apply-go-api.sh /opt/bpm-go-api/apply-go-api.sh
#      sudo install -o deploy -g deploy -d /opt/bpm-go-api/incoming
#      sudo install -m 0440 sudoers-bpm-go-api /etc/sudoers.d/bpm-go-api && sudo visudo -c
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SRC=/opt/bpm-go-api/incoming/go-api-linux
DEST=/opt/bpm-go-api/go-api

if [ ! -f "$SRC" ]; then
    echo "apply-go-api: $SRC یافت نشد — چیزی برای نصب نیست" >&2
    exit 1
fi

install -o www-data -g www-data -m 0755 "$SRC" "$DEST"
rm -f "$SRC"

systemctl restart bpm-go-api
sleep 1
systemctl is-active bpm-go-api
echo "apply-go-api: go-api به‌روزرسانی و ری‌استارت شد"
