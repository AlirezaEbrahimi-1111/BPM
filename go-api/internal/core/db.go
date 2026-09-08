package core

import (
	"database/sql"
	"fmt"
	"log"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

// OpenDB یک استخرِ اتصالِ MariaDB باز می‌کند (همان دیتابیسِ اپِ اصلی).
// parseTime + loc=Local تا time.Time درست از/به DATETIME نگاشت شود.
func OpenDB(c Config) *sql.DB {
	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&loc=Local",
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
