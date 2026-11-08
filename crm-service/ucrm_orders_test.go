package main

import "testing"

func TestShygunOrderStatus(t *testing.T) {
	l := func(q, final, remain float64) shygunOrderLine {
		return shygunOrderLine{Quan: q, FinalOrderQuan: final, OrderRemain1: remain}
	}
	cases := []struct {
		name string
		o    shygunOrderInv
		want string
	}{
		{"not controlled", shygunOrderInv{ControlCheck: false, Body: []shygunOrderLine{l(1, 1, 1)}}, ordStUnchecked},
		{"real sample: all delivered", shygunOrderInv{ControlCheck: true, Body: []shygunOrderLine{l(1, 1, 0)}}, ordStDelivered},
		{"nothing delivered", shygunOrderInv{ControlCheck: true, Body: []shygunOrderLine{l(4, 4, 4)}}, ordStOpen},
		{"partly delivered", shygunOrderInv{ControlCheck: true, Body: []shygunOrderLine{l(4, 4, 1), l(2, 2, 2)}}, ordStPartial},
		{"final qty missing → use Quan", shygunOrderInv{ControlCheck: true, Body: []shygunOrderLine{l(3, 0, 3)}}, ordStOpen},
	}
	for _, c := range cases {
		if got := shygunOrderStatus(c.o); got != c.want {
			t.Errorf("%s: got %s want %s", c.name, got, c.want)
		}
	}
}
