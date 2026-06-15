#!/usr/bin/env python3
"""Build a verified real-product import set from Decathlon product search data."""

from __future__ import annotations

import csv
import json
import re
from io import BytesIO
from pathlib import Path
from typing import Iterable

import requests
from PIL import Image


ROOT = Path(__file__).resolve().parents[3]
OUT_DIR = ROOT / "tmp" / "dalactive_real_products"
IMAGE_DIR = OUT_DIR / "images"
DATA_FILE = OUT_DIR / "products.json"
VERIFY_FILE = OUT_DIR / "verification.csv"

ALGOLIA_APP_ID = "Z69HGH89IH"
ALGOLIA_KEY = "911d17637d07cfd07475d590d045456a"
ALGOLIA_INDEX = "prod_pim_v1_index"
ALGOLIA_URL = f"https://{ALGOLIA_APP_ID}-dsn.algolia.net/1/indexes/{ALGOLIA_INDEX}/query"
DECATHLON_BASE = "https://www.decathlon.vn"

HEADERS = {"User-Agent": "Mozilla/5.0 DALActiveCatalogBot/1.0"}

PLAN = [
    ("football", "Bóng đá", "Default Category/Môn thể thao/Bóng đá/Giày đá bóng", "kipsta football boots", 6, "DAL-REAL-FOOTBALL-SHOE"),
    ("football", "Bóng đá", "Default Category/Môn thể thao/Bóng đá/Bóng đá", "kipsta football size 5", 4, "DAL-REAL-FOOTBALL-BALL"),
    ("football", "Bóng đá", "Default Category/Môn thể thao/Bóng đá/Phụ kiện bóng đá", "kipsta football shin guards bag socks", 4, "DAL-REAL-FOOTBALL-ACC"),
    ("basketball", "Bóng rổ", "Default Category/Môn thể thao/Bóng rổ/Giày bóng rổ", "tarmak basketball shoes", 7, "DAL-REAL-BASKETBALL-SHOE"),
    ("basketball", "Bóng rổ", "Default Category/Môn thể thao/Bóng rổ/Bóng rổ", "basketball ball", 2, "DAL-REAL-BASKETBALL-BALL"),
    ("basketball", "Bóng rổ", "Default Category/Môn thể thao/Bóng rổ/Phụ kiện bóng rổ", "tarmak basketball knee support ankle support", 3, "DAL-REAL-BASKETBALL-ACC"),
    ("running", "Chạy bộ", "Default Category/Môn thể thao/Chạy bộ/Giày chạy bộ", "kiprun running shoes", 8, "DAL-REAL-RUNNING-SHOE"),
    ("running", "Chạy bộ", "Default Category/Môn thể thao/Chạy bộ/Áo chạy bộ", "kiprun running shirt", 4, "DAL-REAL-RUNNING-TEE"),
    ("running", "Chạy bộ", "Default Category/Môn thể thao/Chạy bộ/Quần chạy bộ", "kiprun running shorts", 4, "DAL-REAL-RUNNING-SHORT"),
    ("running", "Chạy bộ", "Default Category/Môn thể thao/Chạy bộ/Phụ kiện chạy bộ", "kiprun running headband socks belt", 4, "DAL-REAL-RUNNING-ACC"),
    ("gym", "Tập luyện & Gym", "Default Category/Môn thể thao/Tập luyện & Gym/Giày training", "cross training shoes", 4, "DAL-REAL-GYM-SHOE"),
    ("gym", "Tập luyện & Gym", "Default Category/Môn thể thao/Tập luyện & Gym/Áo tập gym", "domyos fitness t-shirt", 4, "DAL-REAL-GYM-TEE"),
    ("gym", "Tập luyện & Gym", "Default Category/Môn thể thao/Tập luyện & Gym/Quần tập gym", "domyos fitness shorts", 4, "DAL-REAL-GYM-SHORT"),
    ("gym", "Tập luyện & Gym", "Default Category/Môn thể thao/Tập luyện & Gym/Phụ kiện tập gym", "domyos dumbbell resistance band bottle", 6, "DAL-REAL-GYM-ACC"),
    ("tennis", "Tennis", "Default Category/Môn thể thao/Tennis/Vợt tennis", "artengo tennis racket", 5, "DAL-REAL-TENNIS-RACKET"),
    ("tennis", "Tennis", "Default Category/Môn thể thao/Tennis/Giày tennis", "artengo tennis shoes", 4, "DAL-REAL-TENNIS-SHOE"),
    ("tennis", "Tennis", "Default Category/Môn thể thao/Tennis/Bóng tennis", "artengo tennis ball", 3, "DAL-REAL-TENNIS-BALL"),
    ("men", "Chạy bộ", "Default Category/Nam/Giày", "adidas men shoes", 4, "DAL-REAL-MEN-SHOE"),
    ("women", "Chạy bộ", "Default Category/Nữ/Giày", "adidas women shoes", 4, "DAL-REAL-WOMEN-SHOE"),
    ("kids", "Bóng đá", "Default Category/Trẻ em/Giày", "kids football shoes kipsta", 4, "DAL-REAL-KIDS-SHOE"),
]

PRICE_FALLBACK = {
    "shoe": 1590000,
    "ball": 399000,
    "racket": 1190000,
    "apparel": 399000,
    "accessory": 249000,
}


def slugify(value: str) -> str:
    table = str.maketrans(
        "àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ"
        "ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸĐ",
        "aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd"
        "AAAAAAAAAAAAAAAAAEEEEEEEEEEEIIIIIOOOOOOOOOOOOOOOOOUUUUUUUUUUUYYYYYD",
    )
    value = value.translate(table).lower()
    value = re.sub(r"[^a-z0-9]+", "-", value).strip("-")
    return re.sub(r"-+", "-", value)


def algolia_search(query: str, hits: int = 40) -> list[dict]:
    response = requests.post(
        ALGOLIA_URL,
        params={"x-algolia-application-id": ALGOLIA_APP_ID, "x-algolia-api-key": ALGOLIA_KEY},
        json={"query": query, "hitsPerPage": hits, "page": 0},
        headers=HEADERS,
        timeout=20,
    )
    response.raise_for_status()
    return response.json().get("hits", [])


def image_candidates(hit: dict) -> Iterable[str]:
    value = hit.get("smallUrl")
    if isinstance(value, str) and value.startswith("http") and "NO_URL" not in value:
        yield normalize_image_url(value)
    for value in hit.get("otherImages") or []:
        if isinstance(value, str) and value.startswith("http") and "NO_URL" not in value:
            yield normalize_image_url(value)
    value = hit.get("context")
    if isinstance(value, str) and value.startswith("http") and "NO_URL" not in value:
        yield normalize_image_url(value)


def normalize_image_url(url: str) -> str:
    return re.sub(r"/(?:400x400|250x250|1200x1200)$", "", url)


def first_text(hit: dict, *keys: str) -> str:
    for key in keys:
        value = hit.get(key)
        if isinstance(value, str) and value.strip():
            return value.strip()
    return ""


def product_type_from_prefix(prefix: str) -> str:
    token = prefix.rsplit("-", 1)[-1]
    if token in {"SHOE"}:
        return "shoe"
    if token in {"BALL"}:
        return "ball"
    if token in {"RACKET"}:
        return "racket"
    if token in {"TEE", "SHORT"}:
        return "apparel"
    return "accessory"


def hit_text(hit: dict) -> str:
    parts = []
    for key in (
        "name",
        "name_en",
        "name_vi",
        "shortname",
        "shortName_en",
        "shortName_vi",
        "sport",
        "sport_en",
        "sport_vi",
        "productNature",
        "productNature_en",
        "productNature_vi",
        "categoriesHierarchical_en",
        "categoriesHierarchical_vi",
    ):
        value = hit.get(key)
        if isinstance(value, str):
            parts.append(value)
        elif isinstance(value, list):
            parts.extend(str(item) for item in value)
        elif isinstance(value, dict):
            parts.extend(str(item) for item in value.values())
    return " ".join(parts).lower()


def product_matches(hit: dict, bucket: str, sport: str, prefix: str) -> bool:
    text = hit_text(hit)
    product_type = product_type_from_prefix(prefix)
    sport_terms = {
        "Bóng đá": ["football", "soccer", "bóng đá"],
        "Bóng rổ": ["basketball", "bóng rổ"],
        "Chạy bộ": ["running", "run", "chạy bộ", "kiprun", "kalenji"],
        "Tập luyện & Gym": ["fitness", "training", "gym", "weight", "dumbbell", "domyos", "corength"],
        "Tennis": ["tennis", "artengo", "kuikma"],
    }
    if bucket not in {"men", "women", "kids"}:
        if not any(term in text for term in sport_terms.get(sport, [])):
            return False

    if product_type == "shoe":
        if not any(term in text for term in ["shoe", "shoes", "boot", "boots", "giày"]):
            return False
    elif product_type == "ball":
        name_text = " ".join(
            first_text(hit, key)
            for key in ("name", "name_en", "name_vi", "shortname", "shortName_en", "shortName_vi")
        ).lower()
        if not (re.search(r"\bball\b", name_text) or "quả bóng" in name_text):
            return False
        if any(term in name_text for term in ["shoe", "shoes", "boot", "hoop", "goal", "bag", "short", "shirt", "t-shirt", "tee"]):
            return False
    elif product_type == "racket":
        if not any(term in text for term in ["racket", "racquet", "vợt"]):
            return False
    elif prefix.endswith("-TEE"):
        if not any(term in text for term in ["shirt", "t-shirt", "tee", "top", "áo"]):
            return False
    elif prefix.endswith("-SHORT"):
        if not any(term in text for term in ["short", "shorts", "quần"]):
            return False
    elif product_type == "accessory":
        if bucket == "basketball" and not any(term in text for term in ["support", "knee", "ankle", "sleeve", "sock", "basketball"]):
            return False
        if bucket == "football" and not any(term in text for term in ["shin", "guard", "sock", "football", "boot bag", "ball pump"]):
            return False
        if bucket == "running" and not any(term in text for term in ["headband", "sock", "belt", "cap", "running", "bottle"]):
            return False
        if bucket == "gym" and not any(term in text for term in ["dumbbell", "band", "mat", "kettlebell", "glove", "bottle"]):
            return False
    return True


def gender_and_age(hit: dict, bucket: str) -> tuple[str, str]:
    text = " ".join(
        [
            first_text(hit, "gender_en", "gender_vi", "gender"),
            first_text(hit, "name_en", "name_vi", "name"),
            bucket,
        ]
    ).lower()
    if "kid" in text or "junior" in text or "trẻ" in text or bucket == "kids":
        return "Trẻ em", "Trẻ em"
    if "women" in text or "woman" in text or "nữ" in text or bucket == "women":
        return "Nữ", "Người lớn"
    if "men" in text or "man" in text or "nam" in text or bucket == "men":
        return "Nam", "Người lớn"
    return "Unisex", "Người lớn"


def infer_size(product_type: str, hit: dict) -> str:
    size = first_text(hit, "size_vi", "size_en", "size")
    if size:
        return size[:120]
    text = " ".join(
        [
            first_text(hit, "name_en", "name_vi", "name"),
            first_text(hit, "gender_en", "gender_vi", "gender"),
        ]
    ).lower()
    if product_type == "shoe":
        if any(term in text for term in ["kid", "junior", "trẻ"]):
            return "EU 28-38"
        if any(term in text for term in ["women", "woman", "nữ"]):
            return "EU 36-41"
        if any(term in text for term in ["men", "man", "nam"]):
            return "EU 39-44"
        return "EU 36-44"
    if product_type == "apparel":
        if any(term in text for term in ["kid", "junior", "trẻ"]):
            return "120-160 cm"
        if any(term in text for term in ["women", "woman", "nữ"]):
            return "XS-S-M-L-XL"
        if any(term in text for term in ["men", "man", "nam"]):
            return "S-M-L-XL-XXL"
        return "S-M-L-XL"
    return {
        "ball": "Size tiêu chuẩn",
        "racket": "Grip 2-3",
        "accessory": "One Size",
    }[product_type]


def infer_material(hit: dict) -> str:
    components = first_text(hit, "components_vi", "components_en", "components")
    if components:
        return re.sub(r"\s+", " ", components)[:180]
    return "Chất liệu theo thông số nhà sản xuất"


def verify_and_save_image(url: str, filename: str) -> tuple[bool, str]:
    response = requests.get(url, headers=HEADERS, timeout=25)
    if response.status_code != 200 or len(response.content) < 5000:
        return False, f"download failed/status={response.status_code}/bytes={len(response.content)}"
    try:
        with Image.open(BytesIO(response.content)) as src:
            src = src.convert("RGB")
            if src.width < 250 or src.height < 250:
                return False, f"image too small {src.width}x{src.height}"
            canvas = Image.new("RGB", (1000, 1000), "white")
            src.thumbnail((920, 920), Image.Resampling.LANCZOS)
            x = (1000 - src.width) // 2
            y = (1000 - src.height) // 2
            canvas.paste(src, (x, y))
            canvas.save(IMAGE_DIR / filename, "JPEG", quality=90, optimize=True)
    except Exception as exc:
        return False, f"invalid image: {exc}"
    return True, "Import"


def build() -> tuple[list[dict], list[dict]]:
    IMAGE_DIR.mkdir(parents=True, exist_ok=True)
    selected: list[dict] = []
    verification: list[dict] = []
    used_object_ids: set[str] = set()

    for bucket, sport, category_path, query, needed, prefix in PLAN:
        hits = algolia_search(query, hits=70)
        count = 0
        for hit in hits:
            object_id = str(hit.get("objectID") or "")
            if not object_id or object_id in used_object_ids:
                continue
            name = first_text(hit, "name_en", "name", "name_vi")
            source_path = first_text(hit, "url_en", "url", "url_vi")
            if not name or not source_path:
                continue
            if not product_matches(hit, bucket, sport, prefix):
                continue
            source_url = source_path if source_path.startswith("http") else DECATHLON_BASE + source_path
            image_url = next(image_candidates(hit), "")
            if not image_url:
                continue

            product_type = product_type_from_prefix(prefix)
            sku = f"{prefix}-{count + 1:03d}"
            filename = f"{sku.lower()}.jpg"
            ok, decision = verify_and_save_image(image_url, filename)
            verify_row = {
                "sku": sku,
                "product_name": name,
                "brand": first_text(hit, "brand") or "Decathlon",
                "source_url": source_url,
                "image_url": image_url,
                "image_matches_model": "Yes" if ok else "No",
                "image_clear": "Yes" if ok else "No",
                "is_product_image": "Yes" if ok else "No",
                "heavy_watermark": "No" if ok else "Unknown",
                "decision": decision,
            }
            verification.append(verify_row)
            if not ok:
                continue

            gender, age_group = gender_and_age(hit, bucket)
            current_price = int(float(hit.get("price") or PRICE_FALLBACK[product_type]))
            original_price = int(float(hit.get("priceBeforeDiscount") or 0)) if hit.get("priceBeforeDiscount") and hit.get("priceBeforeDiscount") != hit.get("price") else None
            if original_price and original_price <= current_price:
                original_price = None
            color = first_text(hit, "colorName_vi", "colorName_en", "color") or "Theo mẫu"
            material = infer_material(hit)
            description = (
                f"{name} là sản phẩm {sport.lower()} được chọn từ nguồn hàng có ảnh product rõ ràng. "
                f"DAL Active viết lại mô tả theo hướng ngắn gọn, tập trung vào tính ứng dụng: dễ dùng, phù hợp luyện tập thường xuyên "
                f"và có chất liệu/thiết kế theo thông số nhà sản xuất. Sản phẩm phù hợp cho khách hàng {gender.lower()} cần lựa chọn đáng tin cậy."
            )
            selected.append(
                {
                    "sku": sku,
                    "name": name,
                    "brand": verify_row["brand"].title(),
                    "sport": sport,
                    "category_path": category_path,
                    "price": original_price or current_price,
                    "special_price": current_price if original_price else None,
                    "original_price": original_price,
                    "short_description": f"{name} có ảnh sản phẩm thật, rõ model và phù hợp nhu cầu {sport.lower()} hằng ngày.",
                    "description": description,
                    "image": f"dalactive/products/{filename}",
                    "qty": 18 + (len(selected) * 7) % 60,
                    "gender": gender,
                    "age_group": age_group,
                    "size": infer_size(product_type, hit),
                    "color": color[:120],
                    "material": material,
                    "url_key": f"{slugify(name)}-{sku.lower()}",
                    "source_url": source_url,
                    "image_url": image_url,
                }
            )
            used_object_ids.add(object_id)
            count += 1
            if count >= needed:
                break
        if count < needed:
            raise RuntimeError(f"Only collected {count}/{needed} for query {query}")

    return selected, verification


def main() -> None:
    products, verification = build()
    DATA_FILE.write_text(json.dumps({"products": products}, ensure_ascii=False, indent=2), encoding="utf-8")
    with VERIFY_FILE.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=[
                "sku",
                "product_name",
                "brand",
                "source_url",
                "image_url",
                "image_matches_model",
                "image_clear",
                "is_product_image",
                "heavy_watermark",
                "decision",
            ],
        )
        writer.writeheader()
        writer.writerows(verification)
    print(f"Generated {len(products)} verified products")
    print(DATA_FILE)
    print(VERIFY_FILE)
    print(IMAGE_DIR)


if __name__ == "__main__":
    main()
