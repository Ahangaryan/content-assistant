<div align="center">

# AI for WordPress

A content assistant for WordPress. Write, rewrite, and improve copy with any OpenAI-compatible service, without leaving the editor.

<br>

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![Version](https://img.shields.io/badge/version-1.2.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-3da639)

**[English](README.md)** · [فارسی](README.fa.md)

</div>

---

## Overview

The plugin connects WordPress to an OpenAI-compatible API and puts the result where the writing actually happens: inside the post editor, in a sidebar panel or a classic meta box. Suggestions and rewrites appear next to your content, and nothing is saved until you confirm it.

Beyond the editor, two workflows cover the routine work: a daily schedule that publishes on its own, and a bulk editor that goes through posts and fills the gaps.

## Features

### Editor assistant

- **Sidebar panel** in the block editor and a **meta box** in the classic editor.
- Suggest a title, generate body text, propose an excerpt, or rewrite just the selected block.
- Run a custom instruction on any field, including custom fields detected on the post.
- SEO review with a score and concrete suggestions, compatible with Yoast SEO, RankMath, and All in One SEO.
- Every result is a preview first — content changes only after you approve it.
- Optional admin-bar shortcut, so the panel is one click away from any admin screen.

### Scheduled publishing

- A daily plan per post type: posts per day, spread inside a time window or dropped at fixed hours.
- Topic list, content language, and publish status per plan.
- Today's slots are shown as they fill up; a plan can be test-run immediately.
- Runs on WP-Cron every five minutes.

### Bulk editor

- Filter posts missing content, a title, an excerpt, a featured image, or a meta description.
- Generate or repair them in one pass, with live progress and an event log.
- A configurable delay between requests keeps API usage predictable.

### Settings and usage

- API key, endpoint, model, temperature, and token limit.
- Content language and separate interface language, both independent of each other.
- Connection test that reports the response immediately.
- The API key is stored encrypted.
- Usage cards for calls, tokens, and generated posts, plus a filterable event log.

## Requirements

| | |
|---|---|
| WordPress | 5.8 or newer (tested up to 6.7) |
| PHP | 7.4 or newer |
| API | Any OpenAI-compatible service and a valid key |
| Cron | WP-Cron, or a real system cron for low-traffic sites |

## Installation

1. Copy the plugin folder into `wp-content/plugins/` and activate it.
2. Open **AI for WordPress → Settings**, enter the API key and model, and run the connection test.
3. To publish on a schedule, create a daily plan per post type under **Scheduling**.
4. To repair existing content, open **Bulk Editor** and choose the filters that matter to you.

## Settings reference

| Setting | Purpose | Default |
|---|---|---|
| API key | Credential for the service, stored encrypted | — |
| Base URL | OpenAI-compatible endpoint | `https://api.gapgpt.app/v1` |
| Model | Text model name | `gpt-4o` |
| Temperature | Creativity of generated text | `0.7` |
| Max tokens | Response length cap | `4096` |
| Content language | Language of the generated copy | Persian |
| Interface language | Language of the plugin screens | Follows WordPress |
| Default post status | Status used for generated posts | Draft |
| Bulk delay | Seconds between bulk requests | `2` |

## FAQ

**Where do generated posts go?**
Into the post type you selected, with the status set in the settings or in the plan. Drafts by default.

**Does anything change without my approval?**
No. The assistant always returns a preview first; applying it is a separate action.

**Does scheduling need traffic?**
WP-Cron runs on visits. On a low-traffic site, point a system cron at `wp-cron.php` every five minutes.

**Are custom fields supported?**
Yes. Fields detected on the post, including custom ones, can be targeted with a custom instruction.

## Changelog

**1.2.0**
- Bilingual interface with an instant language switcher and full RTL/LTR support.
- Redesigned admin screens with tabs, stat cards, and a visual view of daily slots.
- API key status indicator with a show/hide control.
- Improved assistant panel inside the editor.

**1.1.0**
- Bilingual interface and redesigned admin screens.
- Schedule slot view and API key status.

**1.0.0**
- First release.

## License

Released under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) license.

<div align="center">

Built and maintained by **Hamid Ahangaryan** · [ahangaryan.ir](https://ahangaryan.ir)

</div>
