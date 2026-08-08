<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کنسولِ تستِ دستیارِ هوش‌مصنوعی</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link href="<?= asset('../assets/css/custom.css') ?>" rel="stylesheet">
    <style>
        body { padding-top: 20px; padding-bottom: 2rem; }
        .console-wrap { max-width: 720px; margin: 0 auto; padding: 0 1rem; }
        .console-warning {
            background: #fff3cd;
            border: 1px solid #ffe69c;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .85rem;
            margin-bottom: 16px;
        }
        .console-msg {
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 12px;
            font-size: .92rem;
            line-height: 1.8;
        }
        .console-msg.user { background: var(--surface); border: 1px solid var(--border-soft, #e5e0ee); }
        .console-msg.assistant { background: #f4f1f8; }
        .console-msg.error { background: #fbeae7; color: #7a2a1e; }
        .console-msg .meta { font-size: .72rem; color: #888; margin-top: 6px; }
        .console-sources { font-size: .75rem; color: #666; margin-top: 8px; }
        .console-sources li { margin-bottom: 2px; }
    </style>
</head>

<body>
    <div class="console-wrap">
        <h5 class="mt-2 mb-3">کنسولِ تستِ دستیارِ هوش‌مصنوعی</h5>
        <div class="console-warning">
            این یک صفحه‌ی <strong>موقتِ توسعه/تست</strong> است — رابطِ کاربریِ نهایی نیست (طبقِ فازِ ۱ سندِ
            <code>docs/ai-assistant/spec-v1.md</code>). فعلاً فقط سؤالاتِ مربوط به «کارها» پاسخِ واقعی می‌گیرند
            (مثلاً «چند کار باز دارم؟») — سؤالاتِ سندی/آیین‌نامه‌ای هنوز «اطلاعات کافی یافت نشد» می‌دهند، چون
            بازیابیِ سند (Qdrant) هنوز مستقر نشده.
        </div>

        <div id="msgList"></div>

        <div class="d-flex gap-2 mt-2">
            <input type="text" id="questionInput" class="form-control" placeholder="سؤالتان را بنویسید...">
            <button class="btn btn-primary" id="sendBtn" onclick="sendQuestion()">
                <i class="bi bi-send"></i> ارسال
            </button>
        </div>
        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="resetConversation()">
            <i class="bi bi-arrow-counterclockwise"></i> شروعِ گفتگویِ تازه
        </button>
    </div>

    <script>
        var authToken = localStorage.getItem('auth_token');
        if (!authToken) {
            window.location.href = '../index.php';
        }
        var conversationId = null;

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function appendMsg(role, text, extraHtml) {
            var div = document.createElement('div');
            div.className = 'console-msg ' + role;
            div.innerHTML = '<div>' + esc(text) + '</div>' + (extraHtml || '');
            document.getElementById('msgList').appendChild(div);
            div.scrollIntoView({ behavior: 'smooth' });
        }

        function resetConversation() {
            conversationId = null;
            document.getElementById('msgList').innerHTML = '';
        }

        function sendQuestion() {
            var input = document.getElementById('questionInput');
            var question = input.value.trim();
            if (!question) return;

            appendMsg('user', question);
            input.value = '';
            document.getElementById('sendBtn').disabled = true;

            fetch('../api/ai-assistant/ask.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ question: question, conversation_id: conversationId })
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                .then(function(res) {
                    var data = res.data;
                    if (data.conversation_id) conversationId = data.conversation_id;

                    if (!res.ok || !data.success) {
                        appendMsg('error', data.message || 'خطایی رخ داد');
                        return;
                    }

                    var sourcesHtml = '';
                    if (data.sources && data.sources.length) {
                        sourcesHtml = '<ul class="console-sources">' + data.sources.map(function(s) {
                            return '<li>' + (s.type === 'database'
                                ? 'منبع: ماژولِ ' + esc(s.module) + ' (' + esc(s.as_of) + ')'
                                : 'سند: ' + esc(s.doc_name) + ' — صفحه‌ی ' + esc(s.page)) + '</li>';
                        }).join('') + '</ul>';
                    }
                    var meta = '<div class="meta">status: ' + esc(data.status) + ' | conversation_id: ' + esc(data.conversation_id) + ' | log_id: ' + esc(data.log_id) + '</div>';
                    appendMsg('assistant', data.answer, sourcesHtml + meta);
                })
                .catch(function(err) {
                    appendMsg('error', 'خطایِ ارتباط با سرور: ' + err.message);
                })
                .finally(function() {
                    document.getElementById('sendBtn').disabled = false;
                });
        }

        document.getElementById('questionInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') sendQuestion();
        });
    </script>
</body>

</html>
