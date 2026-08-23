import re
import json
import os

filepath = 'app/lib/data/dummy_data.dart'

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Pattern to match Story(id: '...', title: '...', categoryId: '...', content: '''...''')
# Some titles might have double quotes, etc.
pattern = r"Story\(\s*id:\s*['\"]([^'\"]+)['\"],\s*title:\s*['\"](.*?)['\"],\s*categoryId:\s*['\"]([^'\"]+)['\"],\s*content:\s*'''(.*?)''',\s*\)"
matches = re.findall(pattern, content, re.DOTALL)

stories = []
order = 1
for match in matches:
    stories.append({
        'title': match[1].strip(),
        'categoryId': match[2].strip(),
        'content_text': match[3].strip(),
        'order_index': order
    })
    order += 1

print(f"Extracted {len(stories)} stories.")

out_path = 'backend/stories.json'
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(stories, f, ensure_ascii=False, indent=2)
