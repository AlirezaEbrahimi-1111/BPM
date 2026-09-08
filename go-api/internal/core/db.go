package core

import (
	"database/sql"
	"fmt"
	"log"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

// OpenDB یک استخرِ اتصالِ MariaDB باز می‌کند (همان دیتابیسِ اپِ اصلی).
//
// عمداً parseTime نداریم: ستون‌های DATE/DATETIME/TIMESTAMP به‌صورتِ رشتهٔ خامِ
// MySQL ("2026-09-08" / "2026-09-08 12:38:47") خوانده می‌شوند — دقیقاً همان
// چیزی که PDOِ اپِ PHP برمی‌گرداند (STRINGIFY_FETCHES=false، ولی زمان‌ها باز
// هم رشته‌اند). این برای «parity» با endpointهای PHP لازم است.
func OpenDB(c Config) *sql.DB {
	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&loc=Local",
		c.DBUser, c.DBPass, c.DBHost, c.DBPort, c.DBName,
	)
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		log.Fatalf("db: %v", err)
	}
	db.SetMaxOpenConns(20)
	db.SetMaxIdleConns(5)
	db.SetConnMaxLifetime(time.Hour)

	if err := db.Ping(); err != nil {
		log.Fatalf("db: اتصال ناموفق — %v", err)
	}
	return db
}
