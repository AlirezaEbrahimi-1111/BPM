package core

import (
	"log"
	"time"
)

// tehran — لوکیشن زمانی ثابت اپ (includes/auth.php هم با
// date_default_timezone_set('Asia/Tehran') همین را برای همه‌ی PHP اپ ست می‌کند).
// go-api ممکن است روی سروری با تایم‌زون سیستمی متفاوت اجرا شود، پس هرجا
// PHP از date()/strtotime() بدون آرگومان صریح زمان استفاده می‌کرد، اینجا
// باید عمدا از TehranNow() به‌جای time.Now() استفاده شود.
var tehran = mustLoadTehran()

func mustLoadTehran() *time.Location {
	loc, err := time.LoadLocation("Asia/Tehran")
	if err != nil {
		log.Printf("core: بارگذاری Asia/Tehran ناموفق بود، از UTC+3:30 ثابت استفاده می‌شود: %v", err)
		return time.FixedZone("Asia/Tehran", 3*3600+30*60)
	}
	return loc
}

// TehranNow — همین لحظه، به‌وقت تهران.
func TehranNow() time.Time {
	return time.Now().In(tehran)
}
