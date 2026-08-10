    document.getElementById('togglePassword').addEventListener('click', function() {
        const pw = document.getElementById('password'), icon = document.getElementById('eyeIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            pw.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    });

    document.getElementById('forgotBtn').addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('لطفاً جهت بازنشانی رمز عبور، با مدیر سیستم تماس بگیرید.', 'warning');
    });

    async function getClientInfo() {
        const info = {
            user_agent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform || navigator.userAgentData?.platform || 'unknown',
            screen_resolution: screen.width + 'x' + screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            color_depth: screen.colorDepth,
            timestamp_client: new Date().toISOString()
        };
        try {
            const r = await fetch('https://api.ipify.org?format=json', { signal: AbortSignal.timeout(3000) });
            const d = await r.json();
            info.ip = d.ip;
        } catch { info.ip = 'unavailable'; }
        return info;
    }

    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const rememberMe = document.getElementById('rememberMe').checked;

        if (!username || !password) {
            showAlert('لطفاً نام کاربری و رمز عبور را وارد کنید', 'danger');
            return;
        }

        const btn = document.getElementById('loginBtn');
        btn.classList.add('loading');
        btn.disabled = true;

        try {
            const clientInfo = await getClientInfo();
            const response = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password, remember_me: rememberMe, client_info: clientInfo })
            });
            const data = await response.json();

            if (data.success) {
                localStorage.setItem('auth_token', data.token);
                localStorage.setItem('user_info', JSON.stringify(data.user));
                showAlert('ورود موفقیت‌آمیز! در حال انتقال...', 'success');
                setTimeout(() => { window.location.href = 'pages/dashboard-manager.php'; }, 1000);
            } else {
                showAlert(data.message || 'نام کاربری یا رمز عبور اشتباه است', 'danger');
                document.getElementById('password').value = '';
                document.getElementById('password').focus();
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('خطا در ارتباط با سرور', 'danger');
        } finally {
            btn.classList.remove('loading');
            btn.disabled = false;
        }
    });

    // showAlert قبلاً یک پیاده‌سازیِ جداگانه (با آیکن‌هایِ SVG دستی) داشت؛
    // الان فقط یک نام‌مستعارِ نازک برایِ showToastِ مشترکه (از assets/js/alert.js)
    function showAlert(message, type) {
        showToast(message, type === 'danger' ? 'error' : type);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const token = localStorage.getItem('auth_token');
        const justBounced = sessionStorage.getItem('auth_bounce');
        if (justBounced) {
            sessionStorage.removeItem('auth_bounce');
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_info');
            document.getElementById('username').focus();
            return;
        }
        if (token) {
            fetch('api/auth/profile.php', { headers: { 'Authorization': 'Bearer ' + token } })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success === true) {
                        sessionStorage.setItem('auth_bounce', '1');
                        window.location.href = 'pages/dashboard-manager.php';
                    } else {
                        localStorage.removeItem('auth_token');
                        localStorage.removeItem('user_info');
                    }
                })
                .catch(() => {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                });
        }
        document.getElementById('username').focus();
    });

    document.getElementById('username').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('password').focus(); }
    });
    (function(){
    const canvas = document.getElementById('networkCanvas');
    const ctx = canvas.getContext('2d');
    const colors = ['#6c3ff4','#00c9a7','#a78bfa','#38bdf8','#f472b6'];
    let nodes = [];

    function resize(){
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }

    function init(){
        resize();
        nodes = [];
        for(let i = 0; i < 28; i++){
            nodes.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.6,
                vy: (Math.random() - 0.5) * 0.6,
                r: Math.random() * 3 + 2,
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }
    }

    function draw(){
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for(let i = 0; i < nodes.length; i++){
            const n = nodes[i];
            n.x += n.vx; n.y += n.vy;
            if(n.x < 0 || n.x > canvas.width)  n.vx *= -1;
            if(n.y < 0 || n.y > canvas.height) n.vy *= -1;

            for(let j = i+1; j < nodes.length; j++){
                const m = nodes[j];
                const dx = n.x - m.x, dy = n.y - m.y;
                const dist = Math.sqrt(dx*dx + dy*dy);
                if(dist < 130){
                    ctx.beginPath();
                    ctx.moveTo(n.x, n.y);
                    ctx.lineTo(m.x, m.y);
                    ctx.strokeStyle = n.color;
                    ctx.globalAlpha = (1 - dist/130) * 0.4;
                    ctx.lineWidth = 1;
                    ctx.stroke();
                }
            }
            ctx.globalAlpha = 0.8;
            ctx.beginPath();
            ctx.arc(n.x, n.y, n.r, 0, Math.PI*2);
            ctx.fillStyle = n.color;
            ctx.fill();
        }
        ctx.globalAlpha = 1;
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', init);
    init();
    draw();
})();