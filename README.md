# هفت روز (Haftroz) 📚🎙️

**هفت روز هفته، قصه بگو**  
*Tell stories seven days a week*

🌐 **Website:** [haftroz.ir](https://haftroz.ir)

پروژه «هفت روز» یک پلتفرم جمع‌آوری و شنیدن داستان‌های جذاب ایرانی است. این اپلیکیشن با طراحی مدرن و کاربرپسند به کاربران اجازه می‌دهد داستان‌های مختلف را بخوانند، صدای خود را هنگام خواندن داستان ضبط کنند و آن را به سرور اختصاصی ارسال نمایند تا پس از تایید مدیریت، در دسترس دیگران قرار گیرد.

Haftroz is a platform for collecting and listening to engaging Iranian stories. With a modern and user-friendly design, this app allows users to read stories, record their voice while reading, and send it to a dedicated server. Approved stories will be made available for everyone.

---

## 🏗️ ساختار پروژه | Project Structure

این مخزن (Repository) شامل دو بخش اصلی است:
This repository contains two main parts:

1. **`app/`**: سورس کد اپلیکیشن موبایل فلاتر (Flutter Mobile App)
2. **`backend/`**: کدهای سمت سرور شامل اسکریپت‌های PHP و ساختار دیتابیس MariaDB

---

## 🚀 تکنولوژی‌ها | Technologies

- **Frontend:** Flutter (Dart), Material 3 Design
- **Backend:** PHP, Nginx
- **Database:** MariaDB (MySQL)
- **Typography:** Vazirmatn Font (فونت زیبای وزیرمتن)

---

## ⚙️ نصب و راه‌اندازی | Installation & Setup

### سمت سرور (Backend)
جهت نصب و راه‌اندازی سمت سرور، کدهای پوشه‌ی `backend/` را در روت سرور Nginx خود (مثلاً `/var/www/haftroz.ir/api/`) قرار دهید. سپس دیتابیس خود را بر اساس فایل `schema.sql` تنظیم کنید و رمز دیتابیس را در فایل `upload.php` وارد نمایید.

To set up the backend, place the contents of the `backend/` folder in your Nginx server root (e.g., `/var/www/haftroz.ir/api/`). Configure your database using `schema.sql` and update the database credentials in `upload.php`.

### سمت اپلیکیشن (Mobile App)
جهت تغییر آدرس سرور می‌توانید فایل `.env` را در پوشه `app/` ایجاد کرده و مقدار زیر را تنظیم کنید:

To change the server address, create a `.env` file in the `app/` folder and set the following value:
```env
API_BASE_URL=https://haftroz.ir/api/upload.php
```
سپس پروژه را با دستور زیر بیلد بگیرید:
Then build the project:
```bash
flutter build apk --release
```

---
*توسعه داده شده با ❤️ برای فرهنگ قصه‌گویی ایران*
