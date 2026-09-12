package core

import "database/sql"

// ScanRowsToMaps — معادلِ generic برایِ `SELECT *` + PDO::FETCH_ASSOC در PHP:
// هر ردیف را با همان نام‌های واقعیِ ستون (هرچه که در جدول باشد، حتی
// ستون‌هایی که در این پروژه مهاجرت‌شان مستند/migration نشده — مثلِ
// notifications.bypass_self_filter) به یک map تبدیل می‌کند. استفاده از این
// تابع به‌جایِ یک لیستِ ثابتِ ستون، پورت را در برابرِ رانشِ اسکیمای دیتابیس
// (schema drift) ایمن نگه می‌دارد.
func ScanRowsToMaps(rows *sql.Rows) ([]map[string]any, error) {
	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}

	out := []map[string]any{}
	for rows.Next() {
		vals := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range vals {
			ptrs[i] = &vals[i]
		}
		if err := rows.Scan(ptrs...); err != nil {
			return nil, err
		}
		m := make(map[string]any, len(cols))
		for i, c := range cols {
			if b, ok := vals[i].([]byte); ok {
				m[c] = string(b)
			} else {
				m[c] = vals[i]
			}
		}
		out = append(out, m)
	}
	return out, rows.Err()
}
