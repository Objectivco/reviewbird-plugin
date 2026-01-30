=== reviewbird ===
Contributors: reviewbird, clifgriffin
Tags: reviews, woocommerce, product reviews, ratings, customer reviews
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.11
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Powerfully simple product review collection, moderation, and management for WooCommerce.

== Description ==

reviewbird supercharges your WooCommerce reviews

**🌟Key Features:**

* **Automated Review Collection** - Automatically request reviews from customers after purchase
* **Spam Protection** - AI-powered spam detection blocks fake, inappropriate, and low-quality reviews
* **Complaint Catching** - Intercept negative feedback before it goes public, giving you a chance to resolve issues
* **Beautiful Display** - Display your reviews with a beautiful, customizable widget that matches your store design
* **Multimedia Reviews** - Customers can submit reviews with photos and videos so customers can really see what you products look like.
* **Star Ratings** - Seamlessly integrates with WooCommerce so your ratings show up on product pages and catalog pages
* **SEO Rich Snippets** - Automatically adds JSON-LD structured data for rich Google search results
* **Showcases** - Show-off your best reviews in a variety of formats including sliders, tickers, a review wall, etc.
* **Discount Incentives** - Offers discounts to customers that leave a review - or incentivize them to add a photo or video.
* **Import/Export Reviews** - Easily import and export reviews, with all of your data.
* **Google Merchant Feed** - Sync your reviews to Google shopping search results.
* **Translated and Localized** - Translate your reviews to your user's language, automatically.

** 🔭 SEO Optimized **

Reviews from your customers are one of the best sources of SEO content you can get. When customers write excellent reviews, they use the same language other potential buyers are searching for - those long-tail keywords that drive qualified traffic to your store.

- Fresh, Unique Content: Customer reviews naturally include diverse keywords and phrases.
- Photos and Videos: Collect customer photos and videos to enhance your reviews
- Rich Search Results: Display product ratings and review counts in Google results.

** 🛠️Made for Developers by Developers **

We include the shortcodes, filters, and action hooks you need to deeply integrate reviewbird into your store.

Note: Requires a reviewbird account. [Create an account](https://reviewbird.com/#pricing) to get started.

== External Services ==

This plugin connects to the reviewbird API to enable review collection, management, and display features. reviewbird connects to WooCommerce using OAuth and pulls information securely via the REST API. reviewbird extens the native REST API to add additional end points required for full functionality.

**Service Name:** reviewbird API

**Service Provider:** reviewbird
**Website:** https://reviewbird.com
**Terms of Service:** https://reviewbird.com/terms-and-conditions/
**Privacy Policy:** https://reviewbird.com/privacy-policy/
**Cookie Policy:** https://reviewbird.com/cookie-policy/

**Purpose:**
- Verify your reviewbird account
- Allow reviewbird to collect order data to trigger review request emails
- Retrieve review widgets and showcases for your website
- Allow reviewbird to collect product information for review display and product groups
- Manage review submissions and responses

**Data Transmitted:**
- **Account Verification:** Connection status
- **Order Data:** Order ID, customer email, customer name, line items, order date, status, locale
- **Product Data:** Product ID, name, SKU, slug, GTIN, brand, price, attributes (for variations), image URL, permalink, stock status
- **Customer Data:** Email address, name, review content and media

**When Data is Sent:**
- During OAuth connection from reviewbird to WooCommerce
- During initial product sync after connection
- During scheduled order sync
- When you manually sync products
- When the review widget or showcases are loaded onto your website pages

By using this plugin, you agree to the reviewbird Terms and Conditions and Privacy Policy.

**Requirements:**

* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* A reviewbird account (sign up at reviewbird.com)

== Installation ==

1. Upload the `reviewbird` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > reviewbird to connect your store
4. Follow the connection wizard to link your reviewbird account

== Frequently Asked Questions ==

= Do I need a reviewbird account? =

Yes, you need a reviewbird account to use this plugin. You can sign up at [reviewbird.com](https://reviewbird.com);

= Is WooCommerce required? =

Yes, this plugin requires WooCommerce 5.0 or higher to function.

= How do I display reviews on my product pages? =

Once connected, enable the widget in Settings > reviewbird. Reviews will automatically appear on your product pages.

= Can I customize the widget appearance? =

Yes, you can customize colors and styling through your [reviewbird dashboard](https://app.reviewbird.com/dashboard).

== Screenshots ==

1. reviewbird settings page
2. Review widget on product page
3. Star ratings in shop loop

== Changelog ==

= 1.0.11 =
* Compatibility updates for WordPress.org submission

= 1.0.10 =
* Bug fixes and performance improvements

= 1.0.9 =
* Added force reviews open setting
* Improved star rating display

= 1.0.8 =
* Added schema markup support for rich search results
* Performance improvements with Action Scheduler

= 1.0.7 =
* Initial public release

== Upgrade Notice ==

= 1.0.11 =
This version includes compatibility updates for WordPress.org hosting.
