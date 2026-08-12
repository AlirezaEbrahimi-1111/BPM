"""
طبقه‌بندیِ نیت + مسیریابی (بندِ ۲.۱/۴/۶ سند) — قلبِ ai-service.

نکته‌ی حیاتی (بندِ ۶): این ماژول یک Agentِ عمومی نیست. بر اساسِ نیتِ
طبقه‌بندی‌شده، فقط یکی از اندپوینت‌هایِ *ثابتِ* data/ را با پارامترهایِ
استخراج‌شده صدا می‌زند — مدل هرگز اندپوینتِ جدید نمی‌سازد یا پارامترِ
دلخواه تعیین نمی‌کند.
"""

from __future__ import annotations

import logging
from typing import Any

import httpx

import config as cfg
from llm_client import classify_intent, generate_answer
from retriever import retrieve

logger = logging.getLogger("ai-service.orchestrator")


async def fetch_task_summary(auth_token: str) -> dict[str, Any] | None:
    """
    فراخوانیِ api/ai-assistant/data/task-summary.php با توکنِ رله‌شده
    (بندِ ۶ سند — دقیقاً همان مسیرِ Auth/RBACِ استانداردِ BPM، بدونِ
    لایه‌ی احرازِ موازی).
    """
    async with httpx.AsyncClient(timeout=5.0) as client:
        try:
            resp = await client.get(
                f"{cfg.BPM_BASE_URL}/api/ai-assistant/data/task-summary.php",
                headers={"Authorization": f"Bearer {auth_token}"},
            )
            data = resp.json()
            return data if data.get("success") else None
        except Exception as e:  # noqa: BLE001 — هر خطایِ شبکه/پارس باید graceful باشد
            logger.warning("fetch_task_summary failed: %s", e)
            return None


async def handle_question(payload) -> dict[str, Any]:
    """
    ورودی: AskPayload (main.py) — خروجی دقیقاً همان شکلی که ask.php
    (سمتِ PHP) انتظار دارد: {status, answer, sources}.
    """
    try:
        intent = await classify_intent(payload.question, payload.history)
    except Exception as e:  # noqa: BLE001
        logger.error("classify_intent failed: %s", e)
        return {"status": "error", "answer": None, "sources": None}

    context_parts: list[str] = []
    sources: list[dict[str, Any]] = []

    # ─── مسیرِ عملیاتی — طبقِ بندِ ۶: فقط ماژولِ کارها در فازِ ۱ ───
    if intent in ("operational", "combined"):
        task_data = await fetch_task_summary(payload.auth_token)
        if task_data:
            stats = task_data["stats"]
            due_today_or_earlier = task_data.get("due_today_or_earlier_count", stats.get("today"))
            # 🔒 overdue_count (نه stats['overdue']) — چون از رویِ همین فهرستِ
            # ریزِ پایین جمع زده شده، همیشه با اون هم‌خونه؛ stats['overdue'] از
            # TaskManager::getTaskStats میاد که قاعده‌یِ متفاوتی داره (کارهایِ
            # فرآیندی رو نمی‌بینه، تمدیدِ مهلت رو لحاظ نمی‌کنه) و می‌تونه با
            # فهرستِ ریز اختلاف داشته باشه
            overdue_count = task_data.get("overdue_count", stats.get("overdue"))
            context_parts.append(
                "خلاصه‌ی وضعیتِ کارها (لحظه‌ای): "
                f"کارهایِ بازِ فعلی={stats['total']}، تکمیل‌شده={stats['completed']}، "
                f"معوقه (موعدش گذشته، هنوز باز)={overdue_count}. "
                f"«کارهایِ امروز» طبقِ تعریفِ این سازمان یعنی امروز + معوقه‌ها روی‌هم — "
                f"تعدادِ دقیقِ آن {due_today_or_earlier} است (عددِ جداگانه‌یِ stats.today را که فقط "
                "کارهایِ با موعدِ دقیقاً امروز را می‌شمارد نادیده بگیر، مگر صریحاً پرسیده شود). "
                "فهرستِ ریزِ زیر، همینِ دو دسته (معوقه / امروز) رو به‌شکلِ صریح و به‌ازایِ هر کار مشخص کرده — "
                "برایِ سؤالاتِ «کدام کارها معوقه‌اند» یا «چندتا»، مستقیماً از رویِ همون برچسب‌ها بشمار."
            )

            # ریزِ کارها — بدونِ این، مدل نمی‌تواند به سؤالاتی مثلِ «کدام کارها
            # مالِ من است» یا «عنوانِ کارِ معوقه‌ام چیست» جواب بدهد (فقط شمارش
            # کافی نیست). قاعده‌یِ اینکه کدام کارها این‌جا هستند را خودِ
            # task-summary.php پیاده کرده (تعریف‌کننده/ارجاع‌دهنده‌یِ فعال/
            # مسئولِ فعلی، فقط سازمانِ خودش، بدونِ حذف‌شده‌ها).
            #
            # 🔒 قبلاً فقط یک پرچمِ ترکیبیِ «امروز-یا-زودتر» بود که مدل رو در یک
            # تستِ واقعی گیج کرد (نمی‌تونست از رویِ اون تشخیص بده کدوم دقیقاً
            # «معوقه»‌ست، پس به‌جایِ حدس‌زدن، جوابِ ناقص داد) — حالا صریحاً
            # «معوقه» یا «سررسیدِ امروز» می‌نویسیم، نه یک پرچمِ مبهم
            tasks = task_data.get("tasks") or []
            if tasks:
                lines = []
                for t in tasks:
                    if t.get("is_overdue"):
                        due_flag = " — ⚠️ معوقه (موعدش گذشته)"
                    elif t.get("is_due_today"):
                        due_flag = " — سررسید: امروز"
                    else:
                        due_flag = ""
                    lines.append(
                        f"- «{t['title']}» (شناسه {t['id']}) — وضعیت: {t['status']} — "
                        f"نقشِ کاربر: {t['role']} — مهلت: {t['due_date'] or 'نامشخص'}{due_flag}"
                    )
                context_parts.append("فهرستِ ریزِ کارهایِ مرتبط با این کاربر:\n" + "\n".join(lines))

            sources.append({
                "type": "database",
                "module": "کارها",
                "table_or_view": "task-summary",
                "as_of": "اکنون",
            })

    # ─── مسیرِ سندی — طبقِ بندِ ۳/۵: هنوز Qdrant مستقر نشده (بندِ ۲۱) ───
    if intent in ("document", "combined"):
        chunks = await retrieve(payload.question, payload.organization_id, payload.role)
        for c in chunks:
            context_parts.append(c["chunk_text"])
            sources.append({
                "type": "document",
                "doc_name": c["doc_name"],
                "doc_version": c.get("doc_version"),
                "page": c.get("page_or_sheet"),
            })

    # ─── قانونِ بندِ ۷: بدونِ Context، حدس نزن ───
    if not context_parts:
        return {
            "status": "no_info",
            "answer": (
                "اطلاعات کافی برای پاسخ به این سؤال یافت نشد. "
                "(اطلاعات ثبت نشده است یا سند مربوطه بارگذاری نشده است)"
            ),
            "sources": [],
        }

    try:
        answer = await generate_answer(payload.question, context_parts, payload.history)
    except Exception as e:  # noqa: BLE001
        logger.error("generate_answer failed: %s", e)
        return {"status": "error", "answer": None, "sources": None}

    return {"status": "success", "answer": answer, "sources": sources}
