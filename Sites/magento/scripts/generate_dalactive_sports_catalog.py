#!/usr/bin/env python3
"""Generate DAL Active sports demo catalog JSON and clean product packshots."""

from __future__ import annotations

import json
import math
import re
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[3]
OUT_DIR = ROOT / "tmp" / "dalactive_sports_catalog"
IMAGE_DIR = OUT_DIR / "images"
DATA_FILE = OUT_DIR / "products.json"

SPORTS = {
    "football": {
        "name": "Bóng đá",
        "subs": [
            ("Giày đá bóng", "SHOE", "shoe"),
            ("Áo bóng đá", "JERSEY", "shirt"),
            ("Bóng đá", "BALL", "ball"),
            ("Phụ kiện bóng đá", "ACC", "accessory"),
        ],
    },
    "basketball": {
        "name": "Bóng rổ",
        "subs": [
            ("Giày bóng rổ", "SHOE", "shoe"),
            ("Áo bóng rổ", "JERSEY", "shirt"),
            ("Bóng rổ", "BALL", "ball"),
            ("Phụ kiện bóng rổ", "ACC", "accessory"),
        ],
    },
    "running": {
        "name": "Chạy bộ",
        "subs": [
            ("Giày chạy bộ", "SHOE", "shoe"),
            ("Áo chạy bộ", "TEE", "shirt"),
            ("Quần chạy bộ", "SHORT", "shorts"),
            ("Phụ kiện chạy bộ", "ACC", "accessory"),
        ],
    },
    "gym": {
        "name": "Tập luyện & Gym",
        "subs": [
            ("Giày training", "SHOE", "shoe"),
            ("Áo tập gym", "TEE", "shirt"),
            ("Quần tập gym", "SHORT", "shorts"),
            ("Phụ kiện tập gym", "ACC", "accessory"),
        ],
    },
    "tennis": {
        "name": "Tennis",
        "subs": [
            ("Vợt tennis", "RACKET", "racket"),
            ("Giày tennis", "SHOE", "shoe"),
            ("Bóng tennis", "BALL", "ball"),
            ("Phụ kiện tennis", "ACC", "accessory"),
        ],
    },
}

TEMPLATES = {
    ("football", "SHOE"): [
        ("Nike", "Mercurial Speed FG Football Boots", 3290000, 2890000, "Volt/Black", "Synthetic microfibre, TPU plate"),
        ("Adidas", "Predator Control TF Football Shoes", 2890000, 2490000, "Core Black/White", "Hybrid synthetic upper, rubber outsole"),
        ("Puma", "Future Play MG Football Boots", 2190000, None, "Blue/Orange", "Engineered mesh, TPU studs"),
        ("Kipsta", "Agility 900 FG Football Boots", 1490000, 1290000, "White/Red", "PU upper, moulded TPU sole"),
        ("Nike", "Phantom Touch Academy MG Boots", 2590000, None, "White/Blue", "Textured synthetic, multi-ground sole"),
        ("Adidas", "Copa Comfort TF Football Shoes", 1990000, 1690000, "Black/Gold", "Soft synthetic leather, rubber turf sole"),
        ("Puma", "Ultra Fast Turf Football Shoes", 2490000, None, "Yellow/Black", "Lightweight mesh, EVA midsole"),
        ("Mizuno", "Alpha Sprint FG Football Boots", 3150000, 2790000, "Red/White", "Synthetic leather, TPU outsole"),
        ("Kipsta", "Viralto Club TF Football Shoes", 990000, None, "Navy/White", "Durable PU, non-marking rubber"),
        ("Adidas", "X Crazyfast League MG Boots", 2690000, 2390000, "Silver/Green", "Coated textile, multi-ground studs"),
    ],
    ("football", "JERSEY"): [
        ("Nike", "Dri Match Football Jersey", 790000, 650000, "Red", "Recycled polyester"),
        ("Adidas", "Tiro League Training Jersey", 690000, None, "Black", "Moisture-wicking polyester"),
        ("Puma", "Team Final Football Jersey", 850000, 720000, "Royal Blue", "Performance polyester mesh"),
        ("Kipsta", "Club Breathable Football Jersey", 299000, None, "White", "Lightweight polyester"),
        ("Under Armour", "Matchday Compression Tee", 950000, 790000, "Navy", "Polyester elastane blend"),
        ("Nike", "Academy Pro Training Top", 890000, None, "Green", "Dri-fit knit polyester"),
        ("Adidas", "Entrada Club Football Shirt", 490000, 420000, "Yellow", "Recycled polyester"),
        ("Puma", "Liga Core Football Jersey", 590000, None, "Maroon", "Drycell polyester"),
        ("Kipsta", "Essential Match Jersey", 259000, None, "Sky Blue", "Quick-dry polyester"),
        ("Nike", "Strike Elite Football Tee", 1090000, 890000, "Orange", "Stretch polyester mesh"),
    ],
    ("football", "BALL"): [
        ("Nike", "Academy Flight Size 5 Football", 890000, 750000, "White/Orange", "Textured PU casing"),
        ("Adidas", "UCL League Size 5 Football", 990000, None, "White/Blue", "Thermally bonded TPU"),
        ("Puma", "Orbita Match Football", 750000, 650000, "Yellow/Black", "Machine-stitched TPU"),
        ("Kipsta", "F900 Hybrid Football", 499000, None, "White/Red", "Hybrid PU cover"),
        ("Molten", "Europa Training Football", 690000, None, "Silver/Blue", "PU leather, butyl bladder"),
        ("Nike", "Pitch Training Football", 390000, 329000, "Blue/Volt", "Durable synthetic casing"),
        ("Adidas", "Tiro Club Football", 350000, None, "White/Black", "TPU cover, butyl bladder"),
        ("Puma", "TeamFinal Training Ball", 550000, 459000, "Orange/Navy", "TPU casing"),
        ("Kipsta", "First Kick Football Size 5", 259000, None, "White/Green", "PVC cover"),
        ("Mitre", "Delta Match Football", 1190000, 990000, "White/Silver", "Textured PU, latex bladder"),
    ],
    ("football", "ACC"): [
        ("Nike", "Guard Lock Football Shin Guards", 590000, 499000, "Black", "Polypropylene shell, EVA foam"),
        ("Adidas", "Tiro Football Sock Set", 290000, None, "White", "Polyester cotton blend"),
        ("Kipsta", "Captain Armband Set", 99000, None, "Red", "Elastic polyester"),
        ("Puma", "Team Football Boot Bag", 390000, 329000, "Black", "Recycled polyester"),
        ("Nike", "Grip3 Goalkeeper Gloves", 1390000, 1190000, "White/Black", "Latex palm, mesh backhand"),
        ("Adidas", "Predator Training Gloves", 890000, None, "Blue", "Latex grip, textile"),
        ("Kipsta", "Cone Marker Training Set", 199000, None, "Orange", "Flexible PE"),
        ("Puma", "Ultra Light Shin Pads", 450000, 379000, "Yellow", "TPU shell, EVA foam"),
        ("Nike", "Football Equipment Sack", 490000, None, "Navy", "Woven polyester"),
        ("Adidas", "Tiro League Team Socks", 250000, None, "Black", "Recycled polyester cotton"),
    ],
    ("basketball", "SHOE"): [
        ("Nike", "Court Vision Bounce Basketball Shoes", 2790000, 2390000, "White/Black", "Leather synthetic upper, rubber outsole"),
        ("Jordan", "Flight Control Mid Basketball Shoes", 3890000, 3390000, "Black/Red", "Synthetic leather, Air cushioning"),
        ("Nike", "KD Range Elite Basketball Shoes", 4290000, None, "Blue/White", "Engineered mesh, Zoom cushioning"),
        ("Adidas", "Harden Stepback Court Shoes", 2990000, 2590000, "Purple/Black", "Textile upper, Bounce midsole"),
        ("Puma", "Playmaker Pro Basketball Shoes", 2490000, None, "Red/White", "Mesh upper, rubber traction"),
        ("Under Armour", "Curry Flow Guard Shoes", 4490000, 3990000, "Navy/Gold", "UA Warp upper, foam sole"),
        ("Nike", "LeBron Power Forward Shoes", 4590000, None, "Black/Gold", "Knit upper, responsive cushioning"),
        ("Adidas", "Dame Court Drive Shoes", 2690000, 2290000, "Grey/Orange", "Textile mesh, rubber outsole"),
        ("Puma", "All-Pro Nitro Court Shoes", 3590000, None, "White/Green", "Nitro foam, woven upper"),
        ("Decathlon", "Fast 900 Basketball Shoes", 1390000, 1190000, "Black/Blue", "Mesh, EVA cushioning"),
    ],
    ("basketball", "JERSEY"): [
        ("Nike", "Elite Practice Basketball Jersey", 890000, None, "Red", "Dri-fit polyester"),
        ("Jordan", "Jump Court Sleeveless Jersey", 1190000, 990000, "Black", "Lightweight mesh"),
        ("Adidas", "3G Speed Basketball Tank", 650000, None, "White", "Recycled polyester mesh"),
        ("Under Armour", "Baseline Performance Jersey", 790000, 690000, "Royal Blue", "HeatGear polyester"),
        ("Puma", "Hoops Team Jersey", 590000, None, "Orange", "Drycell mesh"),
        ("Decathlon", "Basketball Reversible Jersey", 349000, None, "Navy/White", "Breathable polyester"),
        ("Nike", "Court DNA Shooting Top", 1090000, 890000, "Purple", "Stretch knit polyester"),
        ("Adidas", "Creator 365 Jersey", 990000, None, "Green", "Aeroready polyester"),
        ("Jordan", "Flight Essentials Jersey", 1290000, 1090000, "Red/Black", "Premium mesh"),
        ("Under Armour", "Iso-Chill Hoops Tank", 950000, None, "Grey", "Nylon polyester blend"),
    ],
    ("basketball", "BALL"): [
        ("Spalding", "TF Street Outdoor Basketball", 790000, None, "Orange", "Performance rubber"),
        ("Wilson", "Evolution Indoor Basketball", 1490000, 1290000, "Brown", "Microfiber composite leather"),
        ("Nike", "Elite Tournament Basketball", 1190000, None, "Amber/Black", "Composite leather"),
        ("Adidas", "Pro 3.0 Basketball", 890000, 759000, "Orange/Black", "Synthetic leather"),
        ("Molten", "BG4500 Match Basketball", 1390000, None, "Orange/Cream", "Composite leather"),
        ("Decathlon", "Tarmak BT500 Basketball", 499000, None, "Brown", "Foam rubber composite"),
        ("Spalding", "Neverflat Training Basketball", 990000, 850000, "Orange", "Premium rubber"),
        ("Wilson", "NBA DRV Plus Basketball", 690000, None, "Brown/Black", "Outdoor rubber"),
        ("Nike", "Playground 8P Basketball", 550000, None, "Black/Orange", "Durable rubber"),
        ("Adidas", "All Court 3.0 Basketball", 650000, 549000, "Tan/Black", "Composite cover"),
    ],
    ("basketball", "ACC"): [
        ("Nike", "Pro Dri-Fit Arm Sleeve", 490000, None, "Black", "Nylon elastane"),
        ("Jordan", "Jumpman Wristband Set", 390000, 329000, "Red", "Cotton nylon blend"),
        ("Adidas", "Creator Knee Pad Pair", 690000, None, "White", "EVA foam, polyester"),
        ("Under Armour", "Performance Headband", 290000, None, "Black", "Polyester elastane"),
        ("Spalding", "Ball Carry Net", 199000, None, "Black", "Nylon mesh"),
        ("Wilson", "NBA Ball Pump", 250000, None, "Silver", "Plastic, steel needle"),
        ("Nike", "Elite Basketball Backpack", 1390000, 1190000, "Navy", "Woven polyester"),
        ("Puma", "Hoops Crew Socks", 250000, None, "White", "Cotton polyester"),
        ("Decathlon", "Tarmak Ankle Support", 399000, 349000, "Grey", "Elastic knit"),
        ("Adidas", "Basketball Bottle 750ml", 199000, None, "Clear/Black", "BPA-free plastic"),
    ],
    ("running", "SHOE"): [
        ("Nike", "RunFlow Pegasus Daily Running Shoes", 3290000, 2890000, "White/Blue", "Engineered mesh, foam midsole"),
        ("Adidas", "Ultraboost Energy Running Shoes", 4490000, 3990000, "Black/White", "Primeknit textile, Boost foam"),
        ("New Balance", "Fresh Foam Tempo Running Shoes", 2990000, None, "Grey/Orange", "Mesh upper, Fresh Foam"),
        ("Asics", "Gel Pulse Road Running Shoes", 2590000, 2290000, "Navy/Lime", "Mesh, GEL cushioning"),
        ("Puma", "Deviate Nitro Speed Shoes", 3990000, None, "Red/Black", "Nitro foam, carbon composite plate"),
        ("Kiprun", "KS900 Comfort Running Shoes", 1890000, 1590000, "Blue/White", "Mesh, EVA foam"),
        ("Nike", "Infinity Road Cushion Shoes", 3890000, None, "Black/Volt", "Flyknit, React foam"),
        ("Adidas", "Adizero Tempo Trainer", 3590000, 3190000, "White/Green", "Lightstrike foam, mesh"),
        ("New Balance", "FuelCell Rebel Running Shoes", 3490000, None, "Purple/White", "Knit mesh, FuelCell foam"),
        ("Asics", "Gel Nimbus Cushion Shoes", 4290000, 3790000, "Cream/Blue", "Jacquard mesh, PureGEL"),
    ],
    ("running", "TEE"): [
        ("Nike", "AeroSwift Running Tee", 1090000, 890000, "Volt", "Dri-fit polyester"),
        ("Adidas", "Own The Run Tee", 650000, None, "Black", "Aeroready recycled polyester"),
        ("New Balance", "Accelerate Short Sleeve Tee", 590000, None, "Blue", "Quick-dry polyester"),
        ("Asics", "Core Running Top", 550000, 459000, "White", "Lightweight knit polyester"),
        ("Puma", "Run Cloudspun Tee", 790000, None, "Grey", "Polyester elastane"),
        ("Kiprun", "Dry+ Running T-Shirt", 299000, None, "Orange", "Breathable polyester"),
        ("Under Armour", "Streaker Run Tee", 850000, 690000, "Navy", "UA Microthread polyester"),
        ("Nike", "Miler Long Run Tee", 790000, None, "Green", "Dri-fit knit"),
        ("Adidas", "Runner Graphic Tee", 590000, 499000, "Red", "Recycled polyester"),
        ("Decathlon", "Kiprun Light Race Tee", 399000, None, "Yellow", "Ultralight polyester"),
    ],
    ("running", "SHORT"): [
        ("Nike", "Stride 5 Inch Running Shorts", 1090000, 890000, "Black", "Woven polyester"),
        ("Adidas", "Own The Run Shorts", 790000, None, "Navy", "Recycled polyester"),
        ("New Balance", "Impact Run Shorts", 850000, 720000, "Grey", "Polywoven fabric"),
        ("Asics", "Road 2-in-1 Shorts", 950000, None, "Blue", "Polyester elastane"),
        ("Puma", "Run Favorite Shorts", 650000, None, "Green", "Drycell polyester"),
        ("Kiprun", "Marathon Split Shorts", 399000, 329000, "Black/Red", "Lightweight polyester"),
        ("Under Armour", "Launch Run Shorts", 790000, None, "Orange", "Stretch woven polyester"),
        ("Nike", "Trail Repel Shorts", 1190000, 990000, "Khaki", "Water-repellent nylon"),
        ("Adidas", "Adizero Race Shorts", 990000, None, "White", "Primeblue polyester"),
        ("Decathlon", "Kiprun Breathable Shorts", 299000, None, "Royal Blue", "Quick-dry polyester"),
    ],
    ("running", "ACC"): [
        ("Nike", "Dri-Fit Lightweight Running Cap", 590000, None, "White", "Polyester twill"),
        ("Adidas", "Running Bottle Belt", 690000, 599000, "Black", "Nylon, BPA-free bottle"),
        ("Kiprun", "Phone Running Armband", 199000, None, "Black", "Neoprene"),
        ("New Balance", "Reflective Run Socks", 250000, None, "Grey", "Polyester cotton"),
        ("Asics", "Performance Running Belt", 490000, None, "Navy", "Elastic nylon"),
        ("Puma", "Run Visor Cap", 390000, 329000, "Volt", "Polyester"),
        ("Nike", "Hydration Flask 500ml", 350000, None, "Clear/Blue", "BPA-free plastic"),
        ("Adidas", "AeroReady Wristband Set", 250000, None, "Black/White", "Cotton polyester"),
        ("Kiprun", "Reflective Safety Vest", 299000, None, "Yellow", "Mesh polyester"),
        ("Under Armour", "Run Compression Socks", 390000, 329000, "Black", "Nylon elastane"),
    ],
    ("gym", "SHOE"): [
        ("Nike", "Metcon Power Training Shoes", 3690000, 3290000, "Black/White", "Mesh, rubber wrap outsole"),
        ("Adidas", "Dropset Strength Trainer", 3290000, None, "Grey/Orange", "Textile upper, dual-density midsole"),
        ("Under Armour", "TriBase Reign Training Shoes", 3490000, 2990000, "Navy/White", "Mesh, UA TriBase outsole"),
        ("Puma", "Fuse Flex Training Shoes", 2490000, None, "Red/Black", "Textile mesh, rubber sole"),
        ("Domyos", "Training 900 Gym Shoes", 1290000, 1090000, "Black/Blue", "Mesh, EVA midsole"),
        ("Nike", "Free Metcon Studio Shoes", 3390000, None, "Pink/White", "Flexible knit, foam midsole"),
        ("Adidas", "Rapidmove Trainer", 2890000, 2490000, "White/Green", "Engineered mesh, rubber grip"),
        ("Under Armour", "Project Rock Training Shoes", 4290000, None, "Black/Gold", "Knit upper, HOVR foam"),
        ("Puma", "PWRFrame TR Gym Shoes", 2790000, 2390000, "Blue/Black", "PWRFrame support, rubber outsole"),
        ("Domyos", "Cross Training 500 Shoes", 990000, None, "Grey", "Synthetic mesh, rubber sole"),
    ],
    ("gym", "TEE"): [
        ("Nike", "Flex Training Tee", 790000, None, "Black", "Dri-fit polyester"),
        ("Adidas", "Train Essentials Tee", 590000, 499000, "White", "Aeroready polyester"),
        ("Under Armour", "Tech 2.0 Gym Tee", 650000, None, "Blue", "UA Tech polyester"),
        ("Puma", "Train Cloudspun Tee", 790000, 690000, "Grey", "Polyester elastane"),
        ("Domyos", "Breathable Fitness T-Shirt", 259000, None, "Red", "Quick-dry polyester"),
        ("Nike", "Pro Compression Top", 1090000, 890000, "Navy", "Polyester spandex"),
        ("Adidas", "Designed For Training Tee", 850000, None, "Green", "Recycled polyester"),
        ("Under Armour", "Iso-Chill Training Tee", 990000, 850000, "Orange", "Nylon polyester"),
        ("Puma", "Train Favorite Tee", 490000, None, "Yellow", "Drycell polyester"),
        ("Domyos", "Muscle Fit Gym Tee", 299000, None, "Black/White", "Cotton polyester"),
    ],
    ("gym", "SHORT"): [
        ("Nike", "Flex Woven Training Shorts", 990000, 850000, "Black", "Stretch woven polyester"),
        ("Adidas", "Designed 4 Training Shorts", 790000, None, "Grey", "Recycled polyester"),
        ("Under Armour", "Launch Workout Shorts", 850000, 690000, "Navy", "Polyester elastane"),
        ("Puma", "PWRFleece Training Shorts", 690000, None, "Green", "Cotton polyester fleece"),
        ("Domyos", "Fitness Breathable Shorts", 299000, None, "Blue", "Polyester"),
        ("Nike", "Pro Dri-Fit Shorts", 890000, None, "Red", "Polyester spandex"),
        ("Adidas", "Train Icons Shorts", 950000, 790000, "Black/White", "Aeroready polyester"),
        ("Under Armour", "Rival Terry Gym Shorts", 890000, None, "Grey", "Cotton terry blend"),
        ("Puma", "Train All Day Shorts", 550000, None, "Orange", "Drycell polyester"),
        ("Domyos", "Cross Training 500 Shorts", 399000, 329000, "Black/Blue", "Stretch polyester"),
    ],
    ("gym", "ACC"): [
        ("Domyos", "Training Resistance Band Set", 299000, None, "Black/Red", "Latex rubber"),
        ("Nike", "Fundamental Training Gloves", 590000, 499000, "Black", "Synthetic leather, mesh"),
        ("Adidas", "Performance Bottle 750ml", 250000, None, "Clear/Black", "BPA-free plastic"),
        ("Under Armour", "Contain Gym Sack", 690000, None, "Navy", "Polyester"),
        ("Puma", "Training Grip Gloves", 450000, 379000, "Grey", "PU palm, mesh"),
        ("Domyos", "Skipping Rope Pro", 199000, None, "Black/Blue", "PVC rope, steel bearing"),
        ("Nike", "Intensity Wrist Wraps", 390000, None, "Black/White", "Elastic nylon"),
        ("Adidas", "Yoga Mat 6mm", 790000, 690000, "Green", "TPE foam"),
        ("Under Armour", "Performance Towel", 350000, None, "Black", "Cotton terry"),
        ("Domyos", "Adjustable Hand Grip", 159000, None, "Blue", "Steel spring, plastic handle"),
    ],
    ("tennis", "RACKET"): [
        ("Wilson", "Pro Staff Control Tennis Racket", 4990000, 4490000, "Black", "Graphite composite"),
        ("Wilson", "Clash Flex Tennis Racket", 5290000, None, "Red/Black", "Carbon graphite"),
        ("Babolat", "Pure Aero Spin Tennis Racket", 5190000, 4690000, "Yellow/Black", "Graphite, flax insert"),
        ("Head", "Speed MP Court Tennis Racket", 4890000, None, "White/Black", "Graphene composite"),
        ("Babolat", "Pure Drive Power Tennis Racket", 4990000, 4390000, "Blue", "Graphite"),
        ("Wilson", "Blade Feel Tennis Racket", 4590000, None, "Green/Black", "Braided graphite"),
        ("Head", "Radical Team Tennis Racket", 3890000, 3390000, "Orange/Grey", "Graphite composite"),
        ("Decathlon", "Artengo TR990 Power Racket", 2490000, None, "White/Blue", "Carbon graphite"),
        ("Babolat", "Boost Strike Tennis Racket", 2390000, 1990000, "Red/White", "Graphite"),
        ("Wilson", "Tour Lite Tennis Racket", 1590000, None, "Navy", "Aluminium graphite blend"),
    ],
    ("tennis", "SHOE"): [
        ("Nike", "Court Zoom Tennis Shoes", 2990000, 2590000, "White/Green", "Synthetic mesh, rubber outsole"),
        ("Adidas", "Barricade Court Tennis Shoes", 3290000, None, "White/Black", "Textile upper, Adiwear outsole"),
        ("Babolat", "Jet Mach Tennis Shoes", 3590000, 3190000, "Blue/White", "Matryx mesh, Michelin rubber"),
        ("Asics", "Gel Resolution Court Shoes", 3490000, None, "Red/White", "PU mesh, GEL cushioning"),
        ("Head", "Sprint Pro Tennis Shoes", 2890000, 2490000, "Navy/Orange", "Mesh, lateral TPU support"),
        ("Wilson", "Rush Pro Court Shoes", 3190000, None, "Black/Gold", "Engineered mesh, rubber sole"),
        ("Nike", "Vapor Lite Tennis Shoes", 2490000, 2190000, "White/Pink", "Textile mesh, foam midsole"),
        ("Adidas", "CourtJam Control Shoes", 2190000, None, "Grey/Blue", "Mesh upper, rubber outsole"),
        ("Decathlon", "Artengo TS990 Tennis Shoes", 1790000, 1490000, "White/Red", "PU mesh, EVA midsole"),
        ("Babolat", "Propulse Fury Tennis Shoes", 3390000, None, "Black/Blue", "Synthetic mesh, Michelin outsole"),
    ],
    ("tennis", "BALL"): [
        ("Wilson", "US Open Tennis Ball Can", 250000, None, "Yellow", "Woven felt, pressurised rubber"),
        ("Babolat", "Team All Court Tennis Balls", 220000, None, "Yellow", "Natural rubber, woven felt"),
        ("Head", "Tour XT Tennis Ball Can", 230000, None, "Yellow", "Premium felt rubber"),
        ("Dunlop", "Australian Open Tennis Balls", 260000, 229000, "Yellow", "HD Pro cloth, rubber core"),
        ("Wilson", "Championship Tennis Balls", 190000, None, "Yellow", "Extra duty felt"),
        ("Decathlon", "Artengo TB930 Tennis Balls", 150000, None, "Yellow", "Pressurised rubber"),
        ("Babolat", "Gold Championship Balls", 210000, None, "Yellow", "Felt rubber"),
        ("Head", "Pro Practice Tennis Balls", 180000, None, "Yellow", "Durable felt"),
        ("Wilson", "Starter Orange Tennis Balls", 170000, None, "Orange/Yellow", "Low compression rubber"),
        ("Dunlop", "Fort All Court Tennis Balls", 290000, 249000, "Yellow", "Premium woven felt"),
    ],
    ("tennis", "ACC"): [
        ("Wilson", "Pro Overgrip Tennis Grip", 250000, None, "White", "Polyurethane grip tape"),
        ("Babolat", "RPM Blast Tennis String Set", 450000, 399000, "Black", "Co-polyester string"),
        ("Head", "Core Tennis Backpack", 1190000, None, "Navy", "Polyester"),
        ("Nike", "Court Dri-Fit Wristbands", 350000, None, "White", "Cotton nylon blend"),
        ("Adidas", "Tennis Headband Set", 290000, None, "Black/White", "Cotton polyester"),
        ("Wilson", "Racket Cover Sleeve", 390000, 329000, "Black", "Padded polyester"),
        ("Babolat", "Syntec Pro Replacement Grip", 320000, None, "Black", "PU grip"),
        ("Head", "Tennis Dampener Twin Pack", 190000, None, "Red", "Silicone"),
        ("Decathlon", "Artengo Tennis Ball Basket", 890000, 790000, "Black", "Steel wire, plastic handle"),
        ("Wilson", "Tour Tennis Towel", 450000, None, "White/Red", "Cotton terry"),
    ],
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


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf",
    ]
    for path in candidates:
        if Path(path).exists():
            return ImageFont.truetype(path, size=size)
    return ImageFont.load_default()


def draw_center(draw: ImageDraw.ImageDraw, xy: tuple[int, int], text: str, fnt, fill: str) -> None:
    bbox = draw.textbbox((0, 0), text, font=fnt)
    draw.text((xy[0] - (bbox[2] - bbox[0]) / 2, xy[1] - (bbox[3] - bbox[1]) / 2), text, font=fnt, fill=fill)


def draw_product_shape(draw: ImageDraw.ImageDraw, product_type: str, primary: str, accent: str) -> None:
    if product_type == "shoe":
        draw.rounded_rectangle((190, 470, 690, 595), radius=42, fill=primary, outline="#d6dce5", width=4)
        draw.polygon([(270, 470), (450, 330), (610, 470)], fill=primary, outline="#d6dce5")
        draw.rounded_rectangle((565, 520, 755, 595), radius=34, fill=accent)
        draw.line((300, 455, 510, 455), fill="white", width=8)
        for x in range(330, 500, 44):
            draw.line((x, 425, x + 40, 470), fill="white", width=5)
        for x in range(255, 650, 58):
            draw.rectangle((x, 595, x + 26, 628), fill="#222222")
    elif product_type == "shirt":
        draw.polygon([(300, 270), (405, 235), (495, 235), (600, 270), (560, 420), (535, 650), (365, 650), (340, 420)], fill=primary, outline="#d6dce5")
        draw.polygon([(300, 270), (210, 380), (300, 455), (340, 420)], fill=accent)
        draw.polygon([(600, 270), (690, 380), (600, 455), (560, 420)], fill=accent)
        draw.arc((390, 225, 510, 315), 0, 180, fill="white", width=8)
        draw.line((360, 510, 540, 510), fill="white", width=7)
    elif product_type == "shorts":
        draw.rounded_rectangle((265, 280, 635, 420), radius=36, fill=primary, outline="#d6dce5")
        draw.polygon([(285, 410), (438, 410), (390, 650), (240, 650)], fill=primary, outline="#d6dce5")
        draw.polygon([(462, 410), (615, 410), (660, 650), (510, 650)], fill=accent, outline="#d6dce5")
        draw.line((450, 300, 450, 640), fill="white", width=5)
    elif product_type == "ball":
        draw.ellipse((245, 235, 655, 645), fill=primary, outline="#d6dce5", width=5)
        for angle in range(0, 360, 45):
            x = 450 + 205 * math.cos(math.radians(angle))
            y = 440 + 205 * math.sin(math.radians(angle))
            draw.line((450, 440, x, y), fill=accent, width=8)
        draw.ellipse((355, 345, 545, 535), outline="white", width=8)
    elif product_type == "racket":
        draw.ellipse((285, 145, 615, 535), fill="white", outline=primary, width=18)
        for x in range(330, 585, 36):
            draw.line((x, 175, x, 505), fill="#dce3ec", width=3)
        for y in range(200, 500, 38):
            draw.line((320, y, 580, y), fill="#dce3ec", width=3)
        draw.rounded_rectangle((420, 515, 480, 760), radius=25, fill=accent)
    else:
        draw.rounded_rectangle((245, 275, 655, 610), radius=55, fill=primary, outline="#d6dce5", width=5)
        draw.rounded_rectangle((315, 340, 585, 540), radius=36, fill=accent)
        draw.line((345, 445, 555, 445), fill="white", width=10)


def make_image(product: dict) -> None:
    colors = ["#0b5fff", "#111827", "#ef4444", "#10b981", "#f59e0b", "#7c3aed"]
    idx = int(product["sku"].rsplit("-", 1)[-1]) - 1
    primary = colors[idx % len(colors)]
    accent = colors[(idx + 2) % len(colors)]
    img = Image.new("RGB", (900, 900), "white")
    draw = ImageDraw.Draw(img)
    draw.rectangle((0, 0, 899, 899), outline="#edf1f7", width=2)
    draw_product_shape(draw, product["product_shape"], primary, accent)
    draw_center(draw, (450, 90), "DAL ACTIVE", font(42, True), "#0f172a")
    draw_center(draw, (450, 810), product["sku"], font(26, True), "#111827")
    short_name = product["name"].replace("DAL Active ", "")
    if len(short_name) > 42:
        short_name = short_name[:39].rstrip() + "..."
    draw_center(draw, (450, 850), short_name, font(24), "#475569")
    img.save(IMAGE_DIR / f"{product['sku'].lower()}.jpg", quality=88, optimize=True)


def main() -> None:
    IMAGE_DIR.mkdir(parents=True, exist_ok=True)
    products = []
    for sport_key, sport in SPORTS.items():
        for sub_name, code, shape in sport["subs"]:
            for idx, (brand, label, price, special, color, material) in enumerate(TEMPLATES[(sport_key, code)], start=1):
                sku = f"DAL-{sport_key.upper()}-{code}-{idx:03d}"
                name = f"DAL Active {label}"
                is_apparel = code in {"JERSEY", "TEE", "SHORT"}
                category_path = f"Default Category/Môn thể thao/{sport['name']}/{sub_name}"
                products.append(
                    {
                        "sku": sku,
                        "name": name,
                        "brand": brand,
                        "sport": sport["name"],
                        "category": sub_name,
                        "category_path": category_path,
                        "price": price,
                        "special_price": special,
                        "short_description": f"{name} dành cho vận động hằng ngày, cân bằng giữa độ bền, sự thoải mái và phong cách thể thao hiện đại.",
                        "description": (
                            f"{name} được DAL Active tuyển chọn theo tinh thần thi đấu thực tế tại Việt Nam. "
                            f"Sản phẩm dùng chất liệu {material.lower()}, form dễ phối và độ hoàn thiện phù hợp cho luyện tập, thi đấu phong trào hoặc sử dụng cuối tuần. "
                            "Thiết kế ưu tiên cảm giác chắc chắn, thoáng nhẹ và dễ bảo quản sau mỗi buổi vận động."
                        ),
                        "image": f"dalactive_sports/{sku.lower()}.jpg",
                        "qty": 24 + (idx * 7) % 73,
                        "gender": "Unisex" if not is_apparel else ("Nam" if idx % 3 == 1 else "Nữ" if idx % 3 == 2 else "Unisex"),
                        "size": "S-XXL" if is_apparel else "EU 39-44" if code == "SHOE" else "One Size" if code == "ACC" else "Size 5" if code == "BALL" else "Grip 2-3",
                        "color": color,
                        "material": material,
                        "url_key": f"{slugify(name)}-{sku.lower()}",
                        "product_shape": shape,
                    }
                )
    for product in products:
        make_image(product)
    DATA_FILE.write_text(json.dumps({"products": products}, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Generated {len(products)} products")
    print(f"Data: {DATA_FILE}")
    print(f"Images: {IMAGE_DIR}")


if __name__ == "__main__":
    main()
