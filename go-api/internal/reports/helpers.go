package reports

import (
	"fmt"
	"math"
	"strconv"
)

// round1 — معادلِ round($x, 1) در PHP.
//
// نکته‌ی سازگاری: PHP با serialize_precision=-1 مقدارِ float(5.0) را «5»
// چاپ می‌کند نه «5.0» — یعنی دقیقاً همان کاری که encoding/json در Go
// می‌کند. پس این‌جا نیازی به نوعِ سفارشی برایِ Marshal نیست.
func round1(v float64) float64 {
	return math.Round(v*10) / 10
}

// fmtAny — ساختِ همان کلیدِ رشته‌ایِ گروه‌بندی که PHP می‌سازد.
// در PHP مقدارها از PDO رشته‌اند و با «.» به هم چسبانده می‌شوند؛ درایورِ
// MySQL در Go برایِ ستونِ INT مقدارِ int64 می‌دهد، پس هر دو حالت پوشش
// داده می‌شود.
func fmtAny(v any) string {
	switch t := v.(type) {
	case nil:
		return ""
	case string:
		return t
	case int64:
		return strconv.FormatInt(t, 10)
	case float64:
		return strconv.FormatFloat(t, 'f', -1, 64)
	case []byte:
		return string(t)
	}
	return fmt.Sprint(v)
}

// mStr / mInt — خواندنِ یک کلید از ردیفِ ScanRowsToMaps، هم‌ارزِ
// `$row['key'] ?? ”` و `(int) $row['key']` در PHP.
func mStr(m map[string]any, key string) string {
	v, ok := m[key]
	if !ok || v == nil {
		return ""
	}
	return fmtAny(v)
}

func mInt(m map[string]any, key string) int64 {
	v, ok := m[key]
	if !ok || v == nil {
		return 0
	}
	switch n := v.(type) {
	case int64:
		return n
	case float64:
		return int64(n)
	case string:
		i, _ := strconv.ParseInt(n, 10, 64)
		return i
	case []byte:
		i, _ := strconv.ParseInt(string(n), 10, 64)
		return i
	}
	return 0
}
