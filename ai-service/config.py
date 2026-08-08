"""
تنظیماتِ ai-service از طریقِ متغیرهایِ محیطی — طبقِ بندِ ۲.۱ سندِ
docs/ai-assistant/spec-v1.md. هیچ‌کدام از این مقادیر نباید هاردکد شوند؛
در استقرارِ واقعی از طریقِ .env یا متغیرهایِ محیطیِ کانتینر تأمین می‌شوند.
"""

import os
from pathlib import Path

from dotenv import load_dotenv

# فایلِ .env (کنارِ همین فایل، نه cwd) — هرگز کامیت نمی‌شود، رجوع کنید به .env.example
load_dotenv(Path(__file__).parent / ".env")

# آدرسِ خودِ BPM (PHP) — برایِ فراخوانیِ اندپوینت‌هایِ data/ با توکنِ رله‌شده
BPM_BASE_URL = os.environ.get("BPM_BASE_URL", "http://localhost:8080")

# باید دقیقاً برابرِ ai_service_secret در config/config.php باشد
AI_SERVICE_SECRET = os.environ.get("AI_SERVICE_SECRET", "")

# پنجره‌ی ضدِ replay برایِ HMAC (ثانیه) — بندِ ۲.۱
SIGNATURE_WINDOW_SECONDS = 30

OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY", "")

# طبقِ بندِ ۲۲.۱ — باید به یک نمایندگیِ منطقه‌ای/پراکسیِ خارج از ایران اشاره کند
OPENAI_BASE_URL = os.environ.get("OPENAI_BASE_URL") or None
