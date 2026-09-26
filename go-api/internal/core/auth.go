package core

import (
	"context"
	"database/sql"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"github.com/golang-jwt/jwt/v5"
)

// AuthUser — کاربر احرازشده در context.
type AuthUser struct {
	ID    int64
	OrgID int64
}

type ctxKey string

const userKey ctxKey = "goapiUser"

func withUser(ctx context.Context, u AuthUser) context.Context {
	return context.WithValue(ctx, userKey, u)
}

// UserOf کاربر احرازشده را از context برمی‌گرداند (بعد از AuthMiddleware).
func UserOf(ctx context.Context) AuthUser {
	u, _ := ctx.Value(userKey).(AuthUser)
	return u
}

// AuthMiddleware — پورت دقیق includes/auth.php::validateToken:
//
//	۱) امضای HS256 با jwt_secret  (کتابخانه بررسی می‌کند)
//	۲) exp لازم و گذشته نباشد     (کتابخانه بررسی می‌کند)
//	۳) SELECT is_active, token_version FROM users WHERE id = user_id
//	   → is_active === 1  و  token_version === claim "tv"
//
// payload توکن اپ: {user_id, organization_id, tv, iat, exp}
func AuthMiddleware(db *sql.DB, secret string) func(http.HandlerFunc) http.HandlerFunc {
	key := []byte(secret)
	return func(next http.HandlerFunc) http.HandlerFunc {
		return func(w http.ResponseWriter, r *http.Request) {
			raw := extractToken(r)
			if raw == "" {
				WriteErr(w, http.StatusUnauthorized, "توکن ارسال نشده است")
				return
			}

			tok, err := jwt.Parse(
				raw,
				func(t *jwt.Token) (any, error) { return key, nil },
				jwt.WithValidMethods([]string{"HS256"}),
				jwt.WithExpirationRequired(),
			)
			if err != nil || !tok.Valid {
				WriteErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
				return
			}
			claims, ok := tok.Claims.(jwt.MapClaims)
			if !ok {
				WriteErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
				return
			}

			uid := ToInt64(claims["user_id"])
			tv := ToInt64(claims["tv"])
			if uid == 0 {
				WriteErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
				return
			}

			var isActive int
			var tokenVersion int64
			err = db.QueryRow(
				"SELECT is_active, token_version FROM users WHERE id = ?", uid,
			).Scan(&isActive, &tokenVersion)
			if err != nil || isActive != 1 || tokenVersion != tv {
				WriteErr(w, http.StatusUnauthorized, "نشست منقضی شده — دوباره وارد شوید")
				return
			}

			u := AuthUser{ID: uid, OrgID: ToInt64(claims["organization_id"])}
			next(w, r.WithContext(withUser(r.Context(), u)))
		}
	}
}

// extractToken — هم‌راستا با includes/auth.php::extractToken: هدر Authorization،
// و در صورت ریدایرکت Apache هدر REDIRECT_HTTP_AUTHORIZATION (که پشت پروکسی
// معمولا همان Authorization می‌رسد، ولی برای اطمینان هر دو را می‌بینیم).
func extractToken(r *http.Request) string {
	h := r.Header.Get("Authorization")
	if h == "" {
		h = r.Header.Get("X-Redirect-Http-Authorization")
	}
	return strings.TrimSpace(strings.TrimPrefix(h, "Bearer "))
}

// ToInt64 — کلایم‌های JSON در Go معمولا float64 دیکود می‌شوند.
func ToInt64(v any) int64 {
	switch n := v.(type) {
	case float64:
		return int64(n)
	case int64:
		return n
	case int:
		return int64(n)
	case json.Number:
		i, _ := n.Int64()
		return i
	case string:
		i, _ := strconv.ParseInt(n, 10, 64)
		return i
	}
	return 0
}
