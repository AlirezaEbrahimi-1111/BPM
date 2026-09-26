"""
Retriever برای اسناد آیین‌نامه/دستورالعمل — Qdrant + BGE-M3 (بند ۲.۱ سند).

⚠️ هنوز به نمونه‌ی واقعی Qdrant/TEI وصل نیست — این‌ها سرویس‌های Dockerی
جداگانه‌اند که طبق بند ۲۱ سند باید قبل از فعال‌شدن این ماژول مستقر شوند.
تا آن زمان retrieve() همیشه فهرست خالی برمی‌گرداند تا Orchestrator بتواند
مسیر صحیح «اطلاعات کافی یافت نشد» (بند ۷) را طی کند — نه خطا بدهد و نه
داده‌ی ساختگی برگرداند.
"""

from __future__ import annotations

from typing import Any


async def retrieve(query: str, organization_id: int, role: str, top_k: int = 5) -> list[dict[str, Any]]:
    """
    خروجی نهایی (پس از اتصال به Qdrant): لیستی از
    {chunk_text, doc_name, doc_version, page_or_sheet}

    ترتیب پیاده‌سازی باقی‌مانده (بند ۲۱/۲.۱ سند):
      ۱) embed(query) از طریق سرویس TEI (BGE-M3)
      ۲) qdrant_client.search(
             collection_name=...,
             query_vector=...,
             query_filter={
                 "must": [
                     {"key": "organization_id", "match": {"value": organization_id}},
                     {"key": "visible_to_roles", "match": {"any": [role]}},
                     {"key": "status", "match": {"value": "active"}},
                 ]
             },
             limit=top_k,
         )
      ۳) نگاشت payload هر نتیجه به شکل بالا
    """
    return []
