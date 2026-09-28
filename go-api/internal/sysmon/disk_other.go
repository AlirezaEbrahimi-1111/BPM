//go:build !linux

package sysmon

import "fmt"

// readDisk — فقط برایِ اینکه پکیج روی ویندوزِ توسعه‌دهنده build/vet/test
// بشه (syscall.Statfs مخصوصِ لینوکسه). این نسخه هیچ‌وقت deploy نمی‌شه —
// deploy.yml همیشه صریحا GOOS=linux کراس‌کامپایل می‌کنه، پس disk_linux.go
// همون‌جاست که واقعا اجرا می‌شه.
func readDisk(path string) (totalBytes, usedBytes uint64, err error) {
	return 0, 0, fmt.Errorf("readDisk: only supported on linux (this build is for local dev only)")
}
