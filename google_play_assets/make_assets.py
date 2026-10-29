import os
import arabic_reshaper
from bidi.algorithm import get_display
from PIL import Image, ImageDraw, ImageFont, ImageFilter

def reshape_text(text):
    if not text:
        return ""
    reshaped = arabic_reshaper.reshape(text)
    return get_display(reshaped)

OUTPUT_DIR = r"C:\Users\Administrator\.gemini\antigravity\scratch\haftroz_full\google_play_assets"
APP_DIR = r"C:\Users\Administrator\.gemini\antigravity\scratch\haftroz_full\app"
FONT_BOLD_PATH = os.path.join(APP_DIR, "assets", "fonts", "Vazirmatn-Bold.ttf")
FONT_REGULAR_PATH = os.path.join(APP_DIR, "assets", "fonts", "Vazirmatn-Regular.ttf")
ICON_PATH = os.path.join(APP_DIR, "assets", "icon.jpg")

os.makedirs(OUTPUT_DIR, exist_ok=True)

# 1. Generate App Icon (512x512, RGB / No Alpha, 32-bit, crisp)
def create_app_icon():
    print("Generating 512x512 App Icon...")
    icon = Image.open(ICON_PATH).convert("RGB")
    icon_512 = icon.resize((512, 512), Image.Resampling.LANCZOS)
    out_path = os.path.join(OUTPUT_DIR, "app_icon_512x512.png")
    icon_512.save(out_path, format="PNG", optimize=True)
    print(f"Saved: {out_path}")

# Helper: Draw rounded rectangle with fill and border
def draw_rounded_rect(draw, bbox, radius, fill=None, outline=None, width=1):
    draw.rounded_rectangle(bbox, radius=radius, fill=fill, outline=outline, width=width)

# Helper: Create vertical gradient
def create_vertical_gradient(width, height, color1, color2):
    base = Image.new("RGB", (width, height), color1)
    top_r, top_g, top_b = color1
    bot_r, bot_g, bot_b = color2
    for y in range(height):
        factor = y / float(height)
        r = int(top_r + (bot_r - top_r) * factor)
        g = int(top_g + (bot_g - top_g) * factor)
        b = int(top_b + (bot_b - top_b) * factor)
        ImageDraw.Draw(base).line([(0, y), (width, y)], fill=(r, g, b))
    return base

# 2. Generate Feature Graphic (1024x500)
def create_feature_graphic():
    print("Generating 1024x500 Feature Graphic...")
    # Warm rich Persian gradient: Dark warm wine/terracotta to deep amber
    width, height = 1024, 500
    base = create_vertical_gradient(width, height, (45, 18, 5), (140, 48, 10))
    draw = ImageDraw.Draw(base)

    # Decorative Persian border
    border_gold = (212, 175, 55)
    border_soft = (180, 140, 40, 100)
    draw.rectangle([(16, 16), (width - 16, height - 16)], outline=border_gold, width=2)
    draw.rectangle([(22, 22), (width - 22, height - 22)], outline=(120, 70, 20), width=1)

    # Corner decorations (Persian brackets)
    corner_len = 35
    for cx, cy in [(22, 22), (width - 22, 22), (22, height - 22), (width - 22, height - 22)]:
        dx = 1 if cx == 22 else -1
        dy = 1 if cy == 22 else -1
        draw.line([(cx, cy), (cx + dx * corner_len, cy)], fill=border_gold, width=3)
        draw.line([(cx, cy), (cx, cy + dy * corner_len)], fill=border_gold, width=3)

    # Load and place App Icon on the right side
    icon = Image.open(ICON_PATH).convert("RGBA")
    icon_size = 280
    icon_resized = icon.resize((icon_size, icon_size), Image.Resampling.LANCZOS)
    
    # Make icon rounded
    mask = Image.new("L", (icon_size, icon_size), 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.rounded_rectangle([(0, 0), (icon_size, icon_size)], radius=50, fill=255)
    
    # Icon position (Right side for Persian RTL layout)
    icon_x = width - icon_size - 70
    icon_y = (height - icon_size) // 2
    
    # Gold border for icon
    border_pad = 4
    draw.rounded_rectangle([
        (icon_x - border_pad, icon_y - border_pad),
        (icon_x + icon_size + border_pad, icon_y + icon_size + border_pad)
    ], radius=54, outline=border_gold, width=4)
    
    base.paste(icon_resized, (icon_x, icon_y), mask)

    # Left side: Typography and Features
    font_title = ImageFont.truetype(FONT_BOLD_PATH, 58)
    font_sub = ImageFont.truetype(FONT_BOLD_PATH, 24)
    font_badge = ImageFont.truetype(FONT_BOLD_PATH, 16)

    # Title
    title_text = reshape_text("اپلیکیشن هفت روز")
    draw.text((70, 85), title_text, fill=(255, 235, 180), font=font_title)

    # Golden divider line
    draw.line([(70, 165), (550, 165)], fill=border_gold, width=3)

    # Subtitle
    sub_text = reshape_text("هفت روز هفته، قصه بشنو و داستان بگو")
    draw.text((70, 185), sub_text, fill=(255, 255, 255), font=font_sub)

    # Secondary text
    desc_text = reshape_text("بزرگ‌ترین گنجینه داستان‌های کهن و عامیانه ایرانی")
    draw.text((70, 230), desc_text, fill=(220, 200, 180), font=font_sub)

    # Feature badges pills
    badges = [
        "بیش از ۷۰۰ قصه کهن و شیرین",
        "استودیوی ضبط صدا و روایتگری",
        "تصویرسازی اختصاصی هوش مصنوعی"
    ]
    
    badge_y = 295
    for badge in badges:
        b_text = reshape_text("✦  " + badge)
        # Draw pill
        draw.rounded_rectangle([(70, badge_y), (520, badge_y + 40)], radius=20, fill=(70, 28, 10), outline=(212, 175, 55), width=1)
        draw.text((85, badge_y + 8), b_text, fill=(255, 240, 200), font=font_badge)
        badge_y += 52

    out_path = os.path.join(OUTPUT_DIR, "feature_graphic_1024x500.png")
    base.save(out_path, format="PNG", optimize=True)
    print(f"Saved: {out_path}")

create_app_icon()
create_feature_graphic()
