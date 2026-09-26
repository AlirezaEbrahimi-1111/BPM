"""
ai-service (FastAPI) — طبق بند ۲.۱/۱۹ سند docs/ai-assistant/spec-v1.md.

⚠️ این سرویس هرگز نباید مستقیما از اینترنت/مرورگر در دسترس باشد — فقط
از سمت api/ai-assistant/ask.php (سرور-به-سرور، با HMAC) فراخوانی می‌شود.
در استقرار، روی یک پورت داخلی (مثلا 127.0.0.1:8100) بایند شود.

اجرای محلی برای تست:
    uvicorn main:app --host 127.0.0.1 --port 8100
"""

from __future__ import annotations

import hashlib
import hmac
import logging
import time

from fastapi import FastAPI, Header, HTTPException, Request
from pydantic import BaseModel

import config as cfg
from orchestrator import handle_question

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("ai-service.main")

app = FastAPI(title="BPM AI Assistant Service")


class AskPayload(BaseModel):
    user_id: int
    organization_id: int
    role: str
    is_manager: bool
    auth_token: str
    question: str
    history: list[dict] = []


def verify_internal_signature(raw_body: bytes, timestamp: str, signature: str) -> None:
    """
    دقیقا هم‌ارز منطق امضای ask.php:
        hash_hmac('sha256', $timestamp . '.' . $body, $secret)
    برای جلوگیری از عدم تطابق، محاسبه روی بایت خام انجام می‌شود (نه
    decode/encode)، تا کاراکترهای فارسی UTF-8 دقیقا همان بایت‌های
    امضاشده در سمت PHP بمانند.
    """
    if not cfg.AI_SERVICE_SECRET:
        logger.error("AI_SERVICE_SECRET is not configured")
        raise HTTPException(status_code=500, detail="AI_SERVICE_SECRET تنظیم نشده است")

    try:
        ts = int(timestamp)
    except (TypeError, ValueError):
        raise HTTPException(status_code=401, detail="هدر زمان نامعتبر است")

    if abs(time.time() - ts) > cfg.SIGNATURE_WINDOW_SECONDS:
        raise HTTPException(status_code=401, detail="درخواست منقضی شده است")

    message = timestamp.encode("utf-8") + b"." + raw_body
    expected = hmac.new(cfg.AI_SERVICE_SECRET.encode("utf-8"), message, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(expected, signature or ""):
        raise HTTPException(status_code=401, detail="امضای نامعتبر")


@app.post("/ask")
async def ask(
    request: Request,
    x_internal_timestamp: str = Header(...),
    x_internal_signature: str = Header(...),
):
    raw_body = await request.body()
    verify_internal_signature(raw_body, x_internal_timestamp, x_internal_signature)

    payload = AskPayload.model_validate_json(raw_body)
    logger.info(
        "ask | user_id=%s org=%s question=%r",
        payload.user_id, payload.organization_id, payload.question[:80],
    )

    result = await handle_question(payload)
    return result


@app.get("/health")
async def health():
    return {"status": "ok"}
