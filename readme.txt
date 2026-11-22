=== هوش مصنوعی برای وردپرس ===
Contributors: hamid-ahangaryan
Author: Hamid Ahangaryan
Author URI: https://ahangaryan.ir
Plugin URI: https://ahangaryan.ir
Tags: ai, content, automation, seo, bilingual
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

دستیار محتوا برای وردپرس: نوشتن، بازنویسی و بهبود متن با هر سرویس سازگار با OpenAI، از داخل ویرایشگر و از روی زمان‌بندی.

== Description ==

افزونه وردپرس را به یک سرویس سازگار با OpenAI وصل می‌کند تا نوشتن و ویرایش محتوا از چند مسیر ساده انجام شود. لازم نیست برای هر کار کوچک بین صفحه‌ها جابه‌جا شوی؛ هر جا متن می‌نویسی، همان‌جا می‌توانی پیشنهاد بگیری، بازنویسی کنی یا کیفیت سئوی مطلب را بسنجی.

هنگام کار روی یک مطلب، پنل کنار ویرایشگر بلوک و متاباکس ویرایشگر کلاسیک عنوان پیشنهادی، متن بدنه، خلاصه، امتیاز سئو و بازنویسی بلوک انتخابی را در دسترس می‌گذارند. روی هر فیلد دیگر هم می‌شود دستور دلخواه نوشت، از جمله فیلدهای سفارشی. هیچ چیز خودکار در پست نوشته نمی‌شود: نتیجه اول نمایش داده می‌شود و فقط با تأیید تو اعمال می‌شود.

برای انتشار منظم می‌توان برای هر پست‌تایپ یک برنامه روزانه ساخت؛ چند مطلب در روز، در یک بازه زمانی یا در ساعت‌های مشخص، با موضوعاتی که خودت انتخاب می‌کنی. اجرا با WP-Cron انجام می‌شود و وضعیت اسلات‌های امروز جلوی چشم است. اگر سایت پر از مطلب نیمه‌کاره باشد، ویرایش گروهی با فیلتر پیدا می‌کند کدام پست‌ها محتوا، عنوان، خلاصه، تصویر شاخص یا توضیح متا ندارند و دسته‌جمعی کاملشان می‌کند.

در تنظیمات، کلید API، آدرس سرویس، مدل، دما، زبان محتوا و زبان رابط تعیین می‌شود و دکمهٔ تست اتصال بلافاصله نتیجه را برمی‌گرداند. کلید API رمزنگاری‌شده ذخیره می‌شود. لاگ رویدادها و آمار مصرف (فراخوانی‌ها، توکن‌ها و مطالب تولیدشده) هم در همان صفحه است تا هزینه‌ها از دستت در نرود. کد افزونه ماژولار نوشته شده — کلاینت API، زمان‌بند، ویرایشگر گروهی، دستیار ویرایشگر و REST API نسخه‌دار هر کدام جدا هستند — و متادیتای سئو با Yoast SEO، RankMath و All in One SEO هماهنگ است.

== English ==

This plugin connects WordPress to any OpenAI-compatible service, so writing and editing content takes a few simple paths instead of a separate tab for every small task.

While working on a post, a sidebar panel in the block editor and a meta box in the classic editor offer a suggested title, body text, an excerpt, an SEO score, and a rewrite of the selected block. A custom instruction can be run on any other field as well, custom fields included. Nothing is written to the post on its own: every result is shown first and applied only after you confirm it.

For steady publishing, each post type can have a daily plan — how many posts per day, inside a time window or at fixed hours, on topics you pick. Runs are handled by WP-Cron, and today's slots are visible at a glance. If the site is full of half-finished posts, the bulk editor finds the ones missing content, a title, an excerpt, a featured image, or a meta description, and completes them as a batch with live progress.

Settings hold the API key, endpoint, model, temperature, content language, and interface language, with a connection test that reports back immediately. The key is stored encrypted. Event logs and usage figures — calls, tokens, generated posts — sit on the same screen so costs stay visible. The code is modular, with the API client, scheduler, bulk editor, editor assistant, and versioned REST API kept apart, and its SEO metadata works with Yoast SEO, RankMath, and All in One SEO.

== Installation ==

= فارسی =
1. پوشهٔ افزونه را در `wp-content/plugins/` قرار بده و فعالش کن.
2. در «هوش مصنوعی ← تنظیمات»، کلید API و مدل را وارد کن و «تست اتصال» را بزن.
3. اگر تولید خودکار می‌خواهی، در «زمان‌بندی تولید» برای هر پست‌تایپ یک برنامه بساز.
4. در «ویرایش گروهی» فیلترها را بگذار و ببین چه چیزهایی برای تکمیل پیدا می‌شود.

= English =
1. Put the plugin folder in `wp-content/plugins/` and activate it.
2. Under Settings, enter the API key and model, then run the connection test.
3. For automatic publishing, build a daily plan per post type under Scheduling.
4. Open Bulk Editor and check what still needs content, titles, or SEO fields.

== Frequently Asked Questions ==

= زبان رابط را چطور عوض کنم؟ =

بالای هر صفحهٔ افزونه یک سوییچر سه‌حالته (خودکار / فارسی / English) هست؛ یا از تنظیمات، گزینهٔ «زبان رابط کاربری» را تغییر بده. هر دو جهت RTL و LTR پشتیبانی می‌شوند.

= How do I change the interface language? =

Every plugin screen has a three-way switcher (Auto / Persian / English). You can also set the interface language under Settings. Both RTL and LTR are supported.

= مطلب تولیدشده کجا ذخیره می‌شود؟ =

در همان پست‌تایپی که انتخاب کرده‌ای، با وضعیتی که در تنظیمات یا زمان‌بندی گذاشته‌ای (پیش‌فرض: پیش‌نویس).

= Where do generated posts go? =

Into the post type you selected, with the status set in the settings or the schedule — drafts by default.

= برای اجرای زمان‌بندی به بازدید نیاز است؟ =

WP-Cron با بازدید سایت اجرا می‌شود. برای سایت کم‌ترافیک بهتر است یک کرون سیستمی هر ۵ دقیقه روی `wp-cron.php` تنظیم کنی.

= Does scheduling need traffic? =

WP-Cron runs on visits. On a low-traffic site, point a real system cron at `wp-cron.php` every five minutes instead.

== Changelog ==

= 1.2.0 =
* رابط دوزبانه با سوییچر فوری زبان و پشتیبانی کامل RTL/LTR.
* بازطراحی صفحات مدیریت با تب‌ها، کارت‌های آماری و نمایش بصری اسلات‌های روزانه.
* نمایش وضعیت کلید API و امکان مخفی کردن آن.
* بهبود پنل دستیار در ویرایشگر.

= 1.1.0 =
* رابط دوزبانه و بازطراحی صفحات مدیریت.
* نمایش اسلات‌های زمان‌بندی و وضعیت کلید API.

= 1.0.0 =
* نسخهٔ اولیه.
