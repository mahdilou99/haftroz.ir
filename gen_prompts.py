import json

def run():
    with open('backend/stories.json', 'r', encoding='utf-8') as f:
        stories = json.load(f)

    with open('story_prompts.txt', 'w', encoding='utf-8') as out:
        out.write('راهنمای تولید عکس با هوش مصنوعی (Nanobanana / Midjourney)\n')
        out.write('=========================================================\n')
        out.write('این پرامپت‌ها بهینه‌سازی شده‌اند تا عکس‌هایی با اتمسفر ایرانی، نقوش اسلیمی، و کیفیت بالا (مربع 1:1) به شما تحویل دهند.\n\n')
        
        for s in stories:
            title = s.get('title', 'داستان')
            prompt = f'An enchanting illustration for a story about "{title}". A beautiful Persian miniature style digital art mixed with modern illustration, featuring intricate Eslimi (Arabesque) patterns and floral motifs, traditional Iranian characters and architecture, non-religious purely cultural Persian art, vibrant colors, highly detailed, magical lighting, rich Persian culture, masterpiece, 4k, --ar 1:1'
            out.write(f'داستان {s.get("id")}: {title}\n')
            out.write(f'{prompt}\n')
            out.write('-' * 50 + '\n')

if __name__ == '__main__':
    run()
