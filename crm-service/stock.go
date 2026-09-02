package main

import "database/sql"

// addStock یک حرکتِ انبار ثبت می‌کند و موجودیِ کش‌شده (inv_stock) را به‌روز می‌کند.
// qty می‌تواند مثبت (ورود) یا منفی (خروج) باشد. باید داخلِ یک تراکنش صدا زده شود.
//
// این‌جا موجودیِ منفی بلاک نمی‌شود — طبقِ تصمیم، کمبودِ موجودی فقط در UI هشدار
// می‌دهد، جلوی ثبت را نمی‌گیرد.
func addStock(tx *sql.Tx, productID, warehouseID int64, qty float64, reason, refType string, refID int64, byUser int64) error {
	if _, err := tx.Exec(`
		INSERT INTO inv_stock_moves (product_id, warehouse_id, qty, reason, ref_type, ref_id, by_user_id)
		VALUES (?, ?, ?, ?, ?, ?, ?)`,
		productID, warehouseID, qty, reason, nullIfEmpty(refType), nullIfZero(refID), byUser); err != nil {
		return err
	}
	_, err := tx.Exec(`
		INSERT INTO inv_stock (product_id, warehouse_id, qty)
		VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)`,
		productID, warehouseID, qty)
	return err
}

func nullIfEmpty(s string) any {
	if s == "" {
		return nil
	}
	return s
}

func nullIfZero(i int64) any {
	if i == 0 {
		return nil
	}
	return i
}
