package main

import "testing"

func TestSmsRenderAndClean(t *testing.T) {
	if got := smsRender("سلام {0}، پیش‌فاکتور {1} آماده است", []string{"علی", "PF-12"}); got != "سلام علی، پیش‌فاکتور PF-12 آماده است" {
		t.Errorf("render: %q", got)
	}
	in := smsTemplateIn{Title: "x", BodyID: 1, Preview: "{0} و {1}", VarLabels: []string{"نام"}}
	if in.clean() == "" {
		t.Error("expected error: 2 placeholders, 1 label")
	}
	in = smsTemplateIn{Title: "x", BodyID: 1, Preview: "{0} و {1}", VarLabels: []string{"نام", " شماره "}}
	if msg := in.clean(); msg != "" || in.VarLabels[1] != "شماره" {
		t.Errorf("clean ok case: %q %v", msg, in.VarLabels)
	}
	in = smsTemplateIn{Title: "x", BodyID: 1, Preview: "بدون متغیر"}
	if msg := in.clean(); msg != "" {
		t.Errorf("no vars: %q", msg)
	}
}
