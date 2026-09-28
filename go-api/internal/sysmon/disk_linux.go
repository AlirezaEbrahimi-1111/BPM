//go:build linux

package sysmon

import "syscall"

// readDisk — پیاده‌سازیِ واقعی، فقط لینوکس (همون‌جایی که این سرویس واقعا
// اجرا می‌شه؛ deploy.yml همیشه GOOS=linux کراس‌کامپایل می‌کنه). فایلِ
// disk_other.go فقط برایِ اینه که روی ویندوزِ توسعه‌دهنده هم build/test
// بشه، هیچ‌وقت واقعا deploy نمی‌شه.
func readDisk(path string) (totalBytes, usedBytes uint64, err error) {
	var stat syscall.Statfs_t
	if err = syscall.Statfs(path, &stat); err != nil {
		return 0, 0, err
	}
	total := stat.Blocks * uint64(stat.Bsize)
	// Bavail (نه Bfree): فضایِ واقعا در دسترسِ کاربرِ عادی، بدونِ سهمیه‌ی رزرو-روت
	avail := stat.Bavail * uint64(stat.Bsize)
	used := total - avail
	return total, used, nil
}
