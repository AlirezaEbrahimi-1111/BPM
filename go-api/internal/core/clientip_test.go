package core

import (
	"net/http/httptest"
	"testing"
)

// ClientIP نباید X-Forwarded-For جعلی کاربر را باور کند (دور زدن لایهٔ IP حضور و غیاب)
func TestClientIP(t *testing.T) {
	cases := []struct {
		name, remote, xff, want string
	}{
		{"behind Apache, no spoof", "127.0.0.1:5000", "5.6.7.8", "5.6.7.8"},
		{"behind Apache, spoofed first value", "127.0.0.1:5000", "1.2.3.4, 5.6.7.8", "5.6.7.8"},
		{"behind Apache IPv6 loopback", "[::1]:5000", "9.9.9.9", "9.9.9.9"},
		{"behind Apache, no header", "127.0.0.1:5000", "", "127.0.0.1"},
		{"direct hit, header ignored", "8.8.8.8:5000", "1.2.3.4", "8.8.8.8"},
	}
	for _, c := range cases {
		r := httptest.NewRequest("POST", "/go/api/attendance/register", nil)
		r.RemoteAddr = c.remote
		if c.xff != "" {
			r.Header.Set("X-Forwarded-For", c.xff)
		}
		if got := ClientIP(r); got != c.want {
			t.Errorf("%s: got %q, want %q", c.name, got, c.want)
		}
	}
}
