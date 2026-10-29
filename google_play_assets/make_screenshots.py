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

# Helper: Create vertical gradient
def create_vertical_gradient(width, height, color1, color2):
    base = Image.new("RGB", (width, height), color1)
    top_r, top_g, top_b = color1
    bot_r, bot_g, bot_b = color2
    draw = ImageDraw.Draw(base)
    for y in range(height):
        factor = y / float(height)
        r = int(top_r + (bot_r - top_r) * factor)
        g = int(top_g + (bot_g - top_g) * factor)
        b = int(top_b + (bot_b - top_b) * factor)
        draw.line([(0, y), (width, y)], fill=(r, g, b))
    return base

def draw_phone_frame(canvas, screen_img, x=90, y=360, width=900, height=1500):
    # Outer dark bezel
    draw = ImageDraw.Draw(canvas)
    bezel_radius = 54
    bezel_thick = 16
    
    # Shadow behind phone
    shadow = Image.new("RGBA", (width + 60, height + 60), (0, 0, 0, 0))
    s_draw = ImageDraw.Draw(shadow)
    s_draw.rounded_rectangle([(15, 15), (width + 45, height + 45)], radius=bezel_radius, fill=(0, 0, 0, 110))
    shadow = shadow.filter(ImageFilter.GaussianBlur(16))
    canvas.paste(shadow, (x - 30, y - 30), shadow)

    # Phone outer frame
    draw.rounded_rectangle([
        (x - bezel_thick, y - bezel_thick),
        (x + width + bezel_thick, y + height + bezel_thick)
    ], radius=bezel_radius + 8, fill=(25, 25, 28), outline=(65, 65, 75), width=3)

    # Screen mask
    screen_resized = screen_img.resize((width, height), Image.Resampling.LANCZOS)
    mask = Image.new("L", (width, height), 0)
    m_draw = ImageDraw.Draw(mask)
    m_draw.rounded_rectangle([(0, 0), (width, height)], radius=bezel_radius, fill=255)
    
    canvas.paste(screen_resized, (x, y), mask)

    # Notch / Camera pill at top
    pill_w, pill_h = 160, 26
    pill_x = x + (width - pill_w) // 2
    pill_y = y + 14
    draw.rounded_rectangle([(pill_x, pill_y), (pill_x + pill_w, pill_y + pill_h)], radius=13, fill=(15, 15, 15))

def render_top_header(canvas, title, subtitle, bg_color1=(230, 81, 0), bg_color2=(45, 18, 5)):
    draw = ImageDraw.Draw(canvas)
    font_title = ImageFont.truetype(FONT_BOLD_PATH, 54)
    font_sub = ImageFont.truetype(FONT_REGULAR_PATH, 28)

    t_text = reshape_text(title)
    s_text = reshape_text(subtitle)

    # Center title
    t_bbox = draw.textbbox((0, 0), t_text, font=font_title)
    t_w = t_bbox[2] - t_bbox[0]
    draw.text(((1080 - t_w) // 2, 110), t_text, fill=(255, 255, 255), font=font_title)

    # Center subtitle
    s_bbox = draw.textbbox((0, 0), s_text, font=font_sub)
    s_w = s_bbox[2] - s_bbox[0]
    draw.text(((1080 - s_w) // 2, 195), s_text, fill=(255, 230, 190), font=font_sub)

    # Gold accent line
    line_w = 120
    draw.line([(540 - line_w // 2, 265), (540 + line_w // 2, 265)], fill=(212, 175, 55), width=4)

# ==================== SCREEN 1: HOME SCREEN ====================
def build_screen_1():
    sw, sh = 900, 1500
    screen = Image.new("RGB", (sw, sh), (248, 249, 250))
    sdraw = ImageDraw.Draw(screen)

    font_b = lambda s: ImageFont.truetype(FONT_BOLD_PATH, s)
    font_r = lambda s: ImageFont.truetype(FONT_REGULAR_PATH, s)

    # Top App Bar Gradient
    appbar = create_vertical_gradient(sw, 190, (230, 81, 0), (191, 54, 12))
    screen.paste(appbar, (0, 0))
    
    # Status bar items
    sdraw.text((sw - 110, 20), "12:45", fill=(255, 255, 255), font=font_b(20))
    sdraw.text((sw - 40, 20), "📶", fill=(255, 255, 255), font=font_b(18))

    # App Bar Title
    title = reshape_text("سلام کاربر گرامی")
    sdraw.text((sw // 2 - 130, 95), title, fill=(255, 255, 255), font=font_b(36))

    # Banner card
    sdraw.rounded_rectangle([(30, 215), (sw - 30, 315)], radius=18, fill=(255, 243, 224), outline=(255, 183, 77), width=2)
    b_text = reshape_text("🎙️  بی‌صبرانه منتظر شنیدن داستان با صدای شما هستیم!")
    sdraw.text((55, 250), b_text, fill=(191, 54, 12), font=font_b(24))

    # Category 1: داستان‌های کهن و پندآموز
    y_off = 345
    sdraw.rounded_rectangle([(30, y_off), (sw - 30, y_off + 350)], radius=20, fill=(255, 255, 255), outline=(230, 230, 230), width=1)
    c1_title = reshape_text("داستان‌های کهن و پندآموز")
    sdraw.text((sw - 360, y_off + 20), c1_title, fill=(30, 30, 30), font=font_b(28))

    # Stories in Category 1
    stories_c1 = [
        ("حکایت مرد خسیس و کیسه زر", "صدای شما ثبت شده است", True),
        ("قصه روباه زیرک و کلاغ ساده‌دل", "متن بازنویسی‌شده و عامیانه", False),
        ("پندهای حکیمانه پیر دانا", "آماده برای ضبط روایت صوتی", False),
    ]

    sy = y_off + 75
    for st_title, sub, rec in stories_c1:
        # Story item box
        sdraw.line([(50, sy), (sw - 50, sy)], fill=(240, 240, 240), width=1)
        # Thumbnail icon
        sdraw.rounded_rectangle([(sw - 125, sy + 10), (sw - 55, sy + 75)], radius=14, fill=(255, 235, 215))
        sdraw.text((sw - 105, sy + 25), "📖", font=font_b(26))
        # Title & Subtitle
        sdraw.text((160, sy + 15), reshape_text(st_title), fill=(0, 128, 0) if rec else (20, 20, 20), font=font_b(22))
        sdraw.text((160, sy + 45), reshape_text(sub), fill=(0, 150, 0) if rec else (130, 130, 130), font=font_r(18))
        # Status icon
        status_ico = "✓" if rec else "›"
        sdraw.text((70, sy + 20), status_ico, fill=(0, 150, 0) if rec else (160, 160, 160), font=font_b(32))
        sy += 88

    # Category 2: لطیفه‌ها و حکایت‌های بهلول
    y_off = 720
    sdraw.rounded_rectangle([(30, y_off), (sw - 30, y_off + 350)], radius=20, fill=(255, 255, 255), outline=(230, 230, 230), width=1)
    c2_title = reshape_text("داستان‌های طنز و حکمت‌آموز بهلول")
    sdraw.text((sw - 420, y_off + 20), c2_title, fill=(30, 30, 30), font=font_b(28))

    stories_c2 = [
        ("قضاوت بهلول بر بوی کباب و صدای درهم", "بسیار جذاب و پرطرفدار", True),
        ("بهلول و حاکم بغداد در بازار مسگران", "روایت عامیانه شیرین", False),
        ("شمارش دیوانگان شهر توسط بهلول", "آماده برای ضبط روایت صوتی", False),
    ]

    sy = y_off + 75
    for st_title, sub, rec in stories_c2:
        sdraw.line([(50, sy), (sw - 50, sy)], fill=(240, 240, 240), width=1)
        sdraw.rounded_rectangle([(sw - 125, sy + 10), (sw - 55, sy + 75)], radius=14, fill=(255, 245, 200))
        sdraw.text((sw - 105, sy + 25), "🎭", font=font_b(26))
        sdraw.text((160, sy + 15), reshape_text(st_title), fill=(0, 128, 0) if rec else (20, 20, 20), font=font_b(22))
        sdraw.text((160, sy + 45), reshape_text(sub), fill=(0, 150, 0) if rec else (130, 130, 130), font=font_r(18))
        status_ico = "✓" if rec else "›"
        sdraw.text((70, sy + 20), status_ico, fill=(0, 150, 0) if rec else (160, 160, 160), font=font_b(32))
        sy += 88

    # Share Button at bottom
    sdraw.rounded_rectangle([(50, 1100), (sw - 50, 1175)], radius=16, fill=(255, 193, 7))
    share_lbl = reshape_text("🔗  ما را با دوستانتان به اشتراک بگذارید")
    sdraw.text((sw // 2 - 210, 1120), share_lbl, fill=(0, 0, 0), font=font_b(24))

    return screen

# ==================== SCREEN 2: STORY SCREEN ====================
def build_screen_2():
    sw, sh = 900, 1500
    screen = Image.new("RGB", (sw, sh), (248, 249, 250))
    sdraw = ImageDraw.Draw(screen)

    font_b = lambda s: ImageFont.truetype(FONT_BOLD_PATH, s)
    font_r = lambda s: ImageFont.truetype(FONT_REGULAR_PATH, s)

    # Top App Bar
    appbar = create_vertical_gradient(sw, 120, (230, 81, 0), (191, 54, 12))
    screen.paste(appbar, (0, 0))
    st_title = reshape_text("قصه قضاوت بهلول و بوی کباب")
    sdraw.text((sw // 2 - 180, 50), st_title, fill=(255, 255, 255), font=font_b(26))
    sdraw.text((sw - 60, 50), "‹", fill=(255, 255, 255), font=font_b(36))

    # Story Artwork Frame
    img_card_y = 150
    sdraw.rounded_rectangle([(40, img_card_y), (sw - 40, img_card_y + 360)], radius=22, fill=(240, 230, 220), outline=(210, 190, 170), width=2)
    # Story Cover
    icon = Image.open(ICON_PATH).convert("RGBA").resize((320, 320), Image.Resampling.LANCZOS)
    screen.paste(icon, (sw // 2 - 160, img_card_y + 20))

    # Story Content Box
    text_y = 540
    sdraw.rounded_rectangle([(40, text_y), (sw - 40, text_y + 550)], radius=22, fill=(255, 255, 255), outline=(230, 230, 230), width=1)
    
    # Story text paragraphs
    p1 = "یکی بود یکی نبود، غیر از خدای مهربون هیچ‌کس نبود..."
    p2 = "یه روز یه بنده خدای گرسنه‌ای داشت از جلوی دکون کبابی رد می‌شد."
    p3 = "بوی کباب حسابی مستش کرده بود، نون خشکی که تو دستش بود رو گرفت"
    p4 = "روی دود کباب و شروع کرد با اشتها خوردن. کبابی که این صحنه رو دید،"
    p5 = "سریع پرید جلو و گفت: آهای آقا! پول بوی کباب من رو باید بدی!"
    p6 = "دعوا بالا گرفت تا اینکه بهلول سر رسید و گفت: غصه نخورید، قضاوت با من..."
    
    sy = text_y + 40
    for p in [p1, p2, p3, p4, p5, p6]:
        sdraw.text((80, sy), reshape_text(p), fill=(40, 40, 40), font=font_r(22))
        sy += 48

    # Quality and Badge tags
    sdraw.rounded_rectangle([(60, text_y + 460), (300, text_y + 510)], radius=14, fill=(235, 245, 235))
    sdraw.text((80, text_y + 472), reshape_text("⭐ امتیاز کیفیت: ۱۰۰ از ۱۰۰"), fill=(30, 120, 30), font=font_b(18))

    sdraw.rounded_rectangle([(320, text_y + 460), (580, text_y + 510)], radius=14, fill=(245, 240, 255))
    sdraw.text((340, text_y + 472), reshape_text("📖 دسته: حکایت‌های بهلول"), fill=(100, 30, 150), font=font_b(18))

    # Bottom Recording CTA
    sdraw.rounded_rectangle([(40, 1120), (sw - 40, 1210)], radius=20, fill=(230, 81, 0))
    sdraw.text((sw // 2 - 180, 1145), reshape_text("🎙️  ورود به استودیوی ضبط صدا"), fill=(255, 255, 255), font=font_b(26))

    return screen

# ==================== SCREEN 3: RECORDING STUDIO ====================
def build_screen_3():
    sw, sh = 900, 1500
    screen = Image.new("RGB", (sw, sh), (248, 249, 250))
    sdraw = ImageDraw.Draw(screen)

    font_b = lambda s: ImageFont.truetype(FONT_BOLD_PATH, s)
    font_r = lambda s: ImageFont.truetype(FONT_REGULAR_PATH, s)

    # Top App Bar
    appbar = create_vertical_gradient(sw, 120, (191, 54, 12), (136, 14, 79))
    screen.paste(appbar, (0, 0))
    st_title = reshape_text("استودیوی اختصاصی ضبط صدا")
    sdraw.text((sw // 2 - 170, 50), st_title, fill=(255, 255, 255), font=font_b(26))
    sdraw.text((sw - 60, 50), "‹", fill=(255, 255, 255), font=font_b(36))

    # Main Studio Card
    card_y = 170
    sdraw.rounded_rectangle([(40, card_y), (sw - 40, card_y + 880)], radius=26, fill=(255, 255, 255), outline=(225, 225, 225), width=2)

    # Studio Title
    sdraw.text((sw // 2 - 150, card_y + 40), reshape_text("روایتگر این داستان باشید!"), fill=(30, 30, 30), font=font_b(32))
    sdraw.text((sw // 2 - 250, card_y + 90), reshape_text("متن را با صدای گرم و لحن صمیمی خود بخوانید"), fill=(110, 110, 110), font=font_r(22))

    # Simulated Audio Waveform (recording in progress)
    wave_y = card_y + 190
    sdraw.rounded_rectangle([(70, wave_y), (sw - 70, wave_y + 130)], radius=18, fill=(250, 240, 245), outline=(230, 190, 210), width=1)
    
    # Draw animated wave bars
    bar_heights = [20, 35, 60, 45, 80, 95, 60, 85, 110, 75, 50, 90, 100, 70, 40, 85, 60, 30, 20]
    bx = 120
    for h in bar_heights:
        sdraw.line([(bx, wave_y + 65 - h // 2), (bx, wave_y + 65 + h // 2)], fill=(216, 27, 96), width=6)
        bx += 35

    sdraw.text((sw // 2 - 60, wave_y + 145), "01:24 / 03:00", fill=(216, 27, 96), font=font_b(22))

    # Big Mic Button (Center CTA)
    btn_cx, btn_cy = sw // 2, card_y + 460
    btn_r = 95
    # Outer pulse
    sdraw.ellipse([(btn_cx - btn_r - 18, btn_cy - btn_r - 18), (btn_cx + btn_r + 18, btn_cy + btn_r + 18)], fill=(255, 235, 238))
    # Inner red button
    sdraw.ellipse([(btn_cx - btn_r, btn_cy - btn_r), (btn_cx + btn_r, btn_cy + btn_r)], fill=(229, 57, 53))
    sdraw.text((btn_cx - 30, btn_cy - 40), "🎙️", font=font_b(54))
    
    # Button Label
    sdraw.text((sw // 2 - 110, card_y + 580), reshape_text("پایان و ذخیره ضبط"), fill=(229, 57, 53), font=font_b(26))

    # Controls: Play preview & Submit
    sdraw.line([(70, card_y + 640), (sw - 70, card_y + 640)], fill=(240, 240, 240), width=1)
    
    # Play Preview button
    sdraw.rounded_rectangle([(70, card_y + 670), (sw // 2 - 15, card_y + 750)], radius=16, fill=(245, 245, 245), outline=(200, 200, 200), width=1)
    sdraw.text((115, card_y + 695), reshape_text("▶  پخش پیش‌نمایش"), fill=(50, 50, 50), font=font_b(22))

    # Submit button
    sdraw.rounded_rectangle([(sw // 2 + 15, card_y + 670), (sw - 70, card_y + 750)], radius=16, fill=(46, 125, 50))
    sdraw.text((sw // 2 + 55, card_y + 695), reshape_text("📤  ارسال برای تایید"), fill=(255, 255, 255), font=font_b(22))

    # Notice at bottom
    sdraw.text((sw // 2 - 270, card_y + 785), reshape_text("✓ پس از تایید مدیر، صدای شما در اپلیکیشن برای همه پخش می‌شود"), fill=(100, 100, 100), font=font_r(18))

    return screen

# ==================== SCREEN 4: LOGIN & COMMUNITY ====================
def build_screen_4():
    sw, sh = 900, 1500
    screen = Image.new("RGB", (sw, sh), (255, 255, 255))
    sdraw = ImageDraw.Draw(screen)

    font_b = lambda s: ImageFont.truetype(FONT_BOLD_PATH, s)
    font_r = lambda s: ImageFont.truetype(FONT_REGULAR_PATH, s)

    # Top decorative graphic
    center_y = 350
    # App Logo
    icon = Image.open(ICON_PATH).convert("RGBA").resize((220, 220), Image.Resampling.LANCZOS)
    screen.paste(icon, (sw // 2 - 110, center_y - 140))

    # Welcome Headlines
    sdraw.text((sw // 2 - 190, center_y + 120), reshape_text("به هفت روز خوش آمدید"), fill=(78, 52, 46), font=font_b(42))
    sdraw.text((sw // 2 - 150, center_y + 185), reshape_text("هفت روز هفته، قصه بگو"), fill=(140, 140, 140), font=font_r(26))

    # Benefit bullet points
    b_y = center_y + 270
    points = [
        "دسترسی به بیش از ۷۰۰ داستان و حکایت شنیدنی",
        "امکان ضبط و ثبت صدای راوی به نام شما",
        "دریافت نشان و انتشار عمومی پس از تایید"
    ]
    for p in points:
        sdraw.rounded_rectangle([(100, b_y), (sw - 100, b_y + 60)], radius=14, fill=(250, 247, 245))
        sdraw.text((120, b_y + 15), reshape_text("⭐  " + p), fill=(60, 60, 60), font=font_b(20))
        b_y += 75

    # Google Sign-In Button
    btn_y = b_y + 60
    sdraw.rounded_rectangle([(90, btn_y), (sw - 90, btn_y + 90)], radius=20, fill=(255, 255, 255), outline=(141, 110, 99), width=2)
    sdraw.text((130, btn_y + 22), "G", fill=(219, 68, 55), font=font_b(42))
    sdraw.text((sw // 2 - 120, btn_y + 28), reshape_text("ورود با حساب گوگل"), fill=(78, 52, 46), font=font_b(28))

    # Safe & Privacy guarantee
    sdraw.text((sw // 2 - 220, btn_y + 130), reshape_text("🔒 ورود کاملاً امن مطابق با استانداردهای گوگل پلی"), fill=(140, 140, 140), font=font_r(18))

    return screen

# ==================== MAIN GENERATOR ====================
def generate_all_screenshots():
    screens_data = [
        ("screenshot_1_home.png", "دنیای بی‌پایان قصه‌های شیرین ایرانی", "بیش از ۷۰۰ قصه کهن، طنز و آموزنده برای تمام سنین", build_screen_1, (230, 81, 0), (78, 52, 46)),
        ("screenshot_2_story.png", "متن‌های روان با تصویرگری اختصاصی", "لحن صمیمی و شیرین همراه با تصاویر چشم‌نواز هوش مصنوعی", build_screen_2, (216, 67, 21), (62, 39, 35)),
        ("screenshot_3_recording.png", "صدای شما، راوی قصه‌های ماندگار!", "استودیوی اختصاصی ضبط صدا و ارسال روایت صوتی برای هر قصه", build_screen_3, (194, 24, 91), (74, 20, 140)),
        ("screenshot_4_login_share.png", "ورود آسان و اشتراک‌گذاری با دوستان", "ورود تک‌کلیکی امن با گوگل و ذخیره سابقه مشارکت‌ها", build_screen_4, (121, 85, 72), (46, 125, 50)),
    ]

    for fname, title, subtitle, builder_fn, c1, c2 in screens_data:
        print(f"Generating showcase screenshot: {fname}...")
        canvas = create_vertical_gradient(1080, 1920, c1, c2)
        render_top_header(canvas, title, subtitle)
        screen_img = builder_fn()
        draw_phone_frame(canvas, screen_img, x=90, y=340, width=900, height=1550)

        out_path = os.path.join(OUTPUT_DIR, fname)
        canvas.save(out_path, format="PNG", optimize=True)
        print(f"Saved: {out_path}")

if __name__ == "__main__":
    generate_all_screenshots()
