=== Frontend Analytics ===
Contributors: stiofansisland, paoltaia, ayecode, basantakumar
Donate link: https://wpgeodirectory.com/
Tags: analytics, google analytics, frontend google analytics, tracking
Requires at least: 4.9
Tested up to: 6.2
Stable tag: 2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays Google Analytics stats on the frontend.

== Description ==

Frontend Google Analytics gives you the ability to add a widget, shortcode or block anywhere on your site that can show selected Google Analytics information.

You can select if the Analytics are show to either "admins","authors", "logged in users" or "all" (including logged out users).

BuddyPress - Can be used on the BuddyPress profile page to show the owner their profile analytics.
UsersWP - Can be used on the UsersWP profile page to show the owner their profile analytics.

== Installation ==

1. Upload 'frontend-analytics' directory to the '/wp-content/plugins/' directory
2. Activate the plugin "Frontend Analytics" through the 'Plugins' menu in WordPress
3. Go to WordPress Admin -> Settings -> Frontend Analytics and customize behaviour as needed

== Screenshots ==

1. This week vs last week.
2. This month vs last month.
3. This year vs last year.
4. Top Countries.

== Changelog ==

= 2.3 - 2023-03-16 =
* Changes for AUI Bootstrap 5 compatibility - ADDED

= 2.2.1 (2022-12-28) =
* Analytics not working for encoded url with special characters - FIXED

= 2.2 (2022-11-15) =
* Graph line & bar color changed - CHANGED
* Google OAuth 2.0 authorization compatibility changes - CHANGED
* Single quote in translations breaks analytics graph - FIXED

= 2.1.1 (2021-10-09) =
* Super Duper v2 causing some issues with builders that use widgets, rolled back to SDv1 to resolve - FIXED

= 2.1.0 (2021-10-08) =
* Prevent the block/widget class loading when not required - CHANGED

= 2.0.1 (2021-05-11) =
* Show/hide visibility for GD listings based on GD packages - FIXED
* Fix conflicts with Uncanny Automator Pro plugin - FIXED
* Chart.js updated to v3.2 - CHANGED

= 2.0.0 (2020-10-08) =
* JavaScript errors breaks analytics when cache is enabled - FIXED
* Changes for AyeCode UI compatibility - CHANGED
* This Month vs Last Month option added in analytics stats view - ADDED

= 1.0.7 =
* Anonymize user IP setting can't be unset once set - FIXED

= 1.0.6 =
* Changes for Google App Verification - CHANGED
* Deauthorize option not showing in settings - FIXED

= 1.0.5 =
* Add support for Author of page or profile page to view stats - ADDED
* Add support BuddyPress profile page - ADDED
* Add support UsersWP profile page - ADDED

= 1.0.4 =
* Block output not always working depending on role selected - FIXED

= 1.0.3 =
* Initial WP.org release - INFO

= 1.0.2 =
* Initial release.