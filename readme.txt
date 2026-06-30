=== Public Library Calendar ===
Contributors: ericgire
Author: Eric Gire
Author URI: https://gistifi.com
Plugin URI: https://gistifi.com/public-library-calendar
Tags: events, calendar, registration, library, rsvp, waitlist
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An events calendar for public libraries with public, no-login event registration, capacity limits, and an automatic waitlist.

== Description ==

Public Library Calendar lets a library publish programs and events and lets patrons sign up without creating an account.

Features:

* **Events** custom post type with date/time, location, capacity, and categories.
* **Public registration** — patrons enter name, email, phone, and party size. No login required.
* **Capacity limits** with an **automatic waitlist**. When a confirmed guest cancels, the oldest waitlisted party that fits is promoted automatically and emailed.
* **Email confirmations** with a one-click cancellation link.
* **Registrants manager** in the admin: view, manually add, cancel, and **export to CSV**.
* **Spam protection** via a honeypot field and nonce-protected submissions.
* **Month-grid or list view** — drop the calendar anywhere with the `[library_calendar]` shortcode.
* **Add to calendar** — an .ics download button on event pages and in confirmation emails (works with Google, Outlook, Apple Calendar).
* **Recurring events** — repeat an event daily, weekly, or monthly; each occurrence is independent with its own capacity and sign-ups.

== Installation ==

1. Upload the `public-library-calendar` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin through the Plugins menu.
3. Go to **Library Calendar → Add Event** to create your first event.
4. Create a page and add the shortcode `[library_calendar]` to display upcoming events.
5. Review **Library Calendar → Settings** to set the confirmation email sender and staff notifications.

== Shortcode ==

`[library_calendar]`

Attributes:

* `view` — `list` (default) for upcoming events, or `grid` for a month calendar.
* `limit` — maximum number of events to show in list view (default 20).
* `category` — comma-separated category slugs to filter by.
* `past` — set to `yes` to include events that have already started (list view).
* `month` — starting month for grid view, e.g. `2026-07`.

Examples:
`[library_calendar limit="10" category="children,teens"]`
`[library_calendar view="grid"]`

== Settings ==

Configure the plugin under **Library Calendar → Settings**:

* **Confirmation emails** — sender name/address and editable subject + message text for both confirmed and waitlisted registrations. Message text supports merge tags: `{event}`, `{name}`, `{date}`, `{location}`, `{party_size}`, `{site}`.
* **Staff notifications** — whether (and where) staff are emailed on each new registration.
* **New event defaults** — default registration on/off, capacity, and waitlist for newly created events.
* **Registration form** — show/hide the phone field, make it required, and set the maximum party size.
* **Calendar page** — the page holding your `[library_calendar]` shortcode; "browse events" and post-cancellation links point here.
* **Advanced** — whether deleting the plugin also deletes all events and registrations (off by default, so data is preserved).

== Theming &amp; developers ==

The front end is built to be restyled and overridden.

**No-code styling.** Under Settings → Appearance you can turn the plugin stylesheet off entirely (let your theme handle everything) and set an accent color used for buttons, badges, and highlights.

**CSS custom properties.** The stylesheet is driven by variables you can override anywhere in your theme CSS:
`--plc-accent`, `--plc-accent-dark`, `--plc-accent-soft`, `--plc-border`, `--plc-muted`, `--plc-radius`.

**Template overrides.** Copy any file from the plugin's `templates/` folder into `your-theme/public-library-calendar/` and edit it there. Overridable templates:

* `event-card.php` — an event in the calendar list
* `event-details.php` — the when/where block on a single event
* `registration-form.php` — the public sign-up form

**Hooks.** Filters: `plc_locate_template`, `plc_calendar_query_args`, `plc_event_card_html`, `plc_event_details_html`, `plc_registration_block_html`, `plc_single_event_html`. Actions: `plc_before_registration_form`, `plc_after_registration_form`. Email and registration flows also pass through standard WordPress hooks.

**Template tags.** Output the calendar directly from a PHP theme template:

`<?php plc_calendar( array( 'view' => 'grid', 'limit' => 10 ) ); ?>` — echoes the calendar.
`<?php $html = plc_get_calendar( array( 'category' => 'teens' ) ); ?>` — returns the HTML.

Both accept the same options as the `[library_calendar]` shortcode (`view`, `limit`, `category`, `past`, `month`) and enqueue the needed assets automatically.

== Recurring events ==

When adding an event, use the **Repeat** box to repeat it daily, weekly, or monthly for a set number of occurrences. Occurrences are generated once when you publish. Each occurrence is a separate event with its own capacity, registration list, and cancellation handling — editing the original afterward does not change occurrences that were already created.

== Frequently Asked Questions ==

= Do patrons need an account to register? =
No. Registration is open to the public with no login.

= What happens when an event is full? =
If the event has a waitlist enabled, additional sign-ups join the waitlist and are emailed. If not, registration closes for that event. When a confirmed guest cancels, waitlisted guests are promoted automatically in order.

= Where is registration data stored? =
In a dedicated database table (`{prefix}_plc_registrations`). Deleting the plugin removes this table and all events.

== Changelog ==

= 1.5.0 =
* New: "Download flyer" produces a printable / save-as-PDF event flyer with the QR code, for sharing and bulletin boards.
* New: share actions on the event page next to "Add to calendar" — download flyer, email, and copy link.
* Improve: the event QR code now sits beside the date/location instead of below it, reducing scrolling.

= 1.4.0 =
* New: optional QR code on each event page (Settings → Appearance) that patrons can scan to open the event. Loads lazily and is filterable via plc_qr_image_url for self-hosting.

= 1.3.0 =
* New: the list view now groups events under a "Today" heading followed by upcoming months, and includes events happening earlier today.
* Change: default accent color is now a neutral slate instead of blue (still adjustable under Settings → Appearance).

= 1.2.2 =
* New: the month grid now previews early next-month events in the grayed-out trailing days, so visitors can see what's coming without changing months.

= 1.2.1 =
* Compatibility: confirmed for WordPress 7.0 (released May 19, 2026); no deprecated APIs in use.

= 1.2.0 =
* New: "Add Event" Dashboard widget for quickly creating an event (with a list of upcoming events) right from the WordPress dashboard.

= 1.1.2 =
* Improve: event times are now chosen from a clear time-of-day dropdown (15-minute increments, AM/PM) instead of a bare spinner. Increment is filterable via plc_time_picker_step.

= 1.1.1 =
* Improve: event date/time entry now uses separate, clearly labeled Date and Time fields instead of the browser's datetime-local control (which hid the time picker on some browsers).
* Fix: on single event pages the WordPress published date is now labeled "Posted on …" so it is not mistaken for the event date.

= 1.1.0 =
* New: plc_calendar() and plc_get_calendar() template tags for embedding the calendar in theme templates.

= 1.0.1 =
* Fix: event start/end dates and registration deadline not saving on some sites. The event editor now uses the classic editor so the date meta box posts reliably.

= 1.0.0 =
* Initial release.
