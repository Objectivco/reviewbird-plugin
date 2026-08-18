=== Reviewbird Product Reviews for WooCommerce ===
Contributors: reviewbird, clifgriffin
Tags: woocommerce, woocommerce product reviews, woocommerce reviews, judge.me, rich snippets, review snippets, SEO
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reviewbird collects, manages and displays reviews for your shop, including photo reviews, in-email review, reminders, rich snippets, and much more.

== Description ==

Reviewbird helps you collect and display star ratings and reviews about your products and WooCommerce store. Social proof from reviews and other user-generated content like photos and videos increases your conversion rate, organic traffic, and buyer engagement. [Create an account](https://reviewbird.com) to get started.

## Collect reviews on autopilot

* Unlimited review requests: schedule automatic requests after fulfillment or delivery, set the delay time for domestic and international orders.
* Email templates: fully-customizable email templates (including in-email review forms)
* Automatic reminders: send email reminders to customers who haven’t left a review yet.
* Coupons: automatically send a static/dynamic coupon to all reviewers that meet your coupon conditions.
* Advanced blacklisting: disable review requests for certain products or customers.
* Manual requests: send unlimited review requests to orders fulfilled prior to the app installation.
* Product groups and cross-shop reviews syndication: share reviews among similar products in the same product groups or shop groups.

## Create High-Quality User Content

* Photo and video reviews: ask reviewers for photos and videos on product pages and in the review request emails.
* Default translations for emails and widgets: available in 24 languages.
* Custom forms: ask custom questions in the review form to understand more about your customers and products.
* AI review summaries, up-votes, and locations for reviews.


## Build trust in your brand

* Review Widget: collect and display your product reviews and star ratings on your product pages.
* Preview Badge: display your star ratings and number of reviews as a badge below the product title on product or collection pages.
* Reviews Showcase: feature selected reviews on your front page, or anywhere you want.
* All Reviews page: Use a review wall showcase to show-off all your reviews.
* Fully optimized for mobile and loading speed.

## Adjust your review style (unique!)

Your brand, not ours: we preserve your brand by using the fonts, styles, and colors as defined by your shop theme.

You can choose among multiple layouts and styles:

1. List view - a standard review list
2. Masonry - a bricks style list of reviews

## Boost your organic traffic

* Automatic rich snippets: show your star ratings in Google Search
* Automatic Google Shopping XML feed: show reviews in Google Shopping.

## Simple migration & great support

* Import directly from Judge.me, Yotpo, WiserReview, Shopify, Stamped.io, Loox, Ryviu, Rivyo, and more.
* Use our review import tool to upload your CSV file or get in touch with our team for migration support.
* Support that cares: When you have a question or an issue, we are on it.

**🌟Key Features:**

* **Automated Review Collection** - Automatically request reviews from customers after purchase
* **Spam Protection** - AI-powered spam detection blocks fake, inappropriate, and low-quality reviews
* **Complaint Catching** - Intercept negative feedback before it goes public, giving you a chance to resolve issues
* **Beautiful Display** - Display your reviews with a beautiful, customizable widget that matches your store design
* **Multimedia Reviews** - Customers can submit reviews with photos and videos so customers can really see what your products look like.
* **Star Ratings** - Seamlessly integrates with WooCommerce so your ratings show up on product pages and catalog pages
* **SEO Rich Snippets** - Automatically adds JSON-LD structured data for rich Google search results
* **Showcases** - Show-off your best reviews in a variety of formats including sliders, tickers, a review wall, etc.
* **Discount Incentives** - Offers discounts to customers that leave a review - or incentivize them to add a photo or video.
* **Import/Export Reviews** - Easily import and export reviews, with all of your data.
* **Google Merchant Feed** - Sync your reviews to Google shopping search results.
* **Translated and Localized** - Translate your reviews to your user's language, automatically.

**🔭 SEO Optimized**

Reviews from your customers are one of the best sources of SEO content you can get. When customers write excellent reviews, they use the same language other potential buyers are searching for - those long-tail keywords that drive qualified traffic to your store.

- Fresh, Unique Content: Customer reviews naturally include diverse keywords and phrases.
- Photos and Videos: Collect customer photos and videos to enhance your reviews
- Rich Search Results: Display product ratings and review counts in Google results.

**🛠️Made for Developers by Developers**

We include the shortcodes, filters, and action hooks you need to deeply integrate Reviewbird into your store.

**📚 Documentation**

Setup guides, shortcode reference, and developer hooks are documented at [reviewbird.com/documentation](https://reviewbird.com/documentation/).

== Shortcodes ==

* `[reviewbird_widget]` — Displays the full review widget. Optional `product_id` attribute (defaults to the current product).
* `[reviewbird_showcase id="123"]` — Displays a review showcase. The `id` attribute is required (get it from your Reviewbird dashboard).
* `[reviewbird_stars]` — Displays the star rating and review count. Optional `product_id` attribute (defaults to the current product).

== External Services ==

This plugin connects to the Reviewbird API to enable review collection, management, and display features. Reviewbird connects to WooCommerce using OAuth and pulls information securely via the REST API. Reviewbird extends the native REST API to add additional endpoints required for full functionality.

**Service Name:** Reviewbird API

**Service Provider:** Reviewbird
**Website:** https://reviewbird.com
**Terms of Service:** https://reviewbird.com/terms-and-conditions/
**Privacy Policy:** https://reviewbird.com/privacy-policy/
**Cookie Policy:** https://reviewbird.com/cookie-policy/

**Purpose:**
- Verify your Reviewbird account
- Prefill account and store setup when you start registration from WordPress
- Allow Reviewbird to collect order data to trigger review request emails
- Retrieve review widgets and showcases for your website
- Allow Reviewbird to collect product information for review display and product groups
- Manage review submissions and responses

**Data Transmitted:**
- **Account Verification:** Connection status
- **Registration Prefill:** Name and email address of the current WordPress user, site name, and public site URL
- **Order Data:** Order ID, customer email, customer name, line items, order date, status, locale
- **Product Data:** Product ID, name, SKU, slug, GTIN, brand, price, attributes (for variations), image URL, permalink, stock status
- **Customer Data:** Email address, name, review content and media

**When Data is Sent:**
- When a WordPress user with permission clicks Create free account on the Reviewbird Get Started page
- During OAuth connection from Reviewbird to WooCommerce
- During initial product sync after connection
- During scheduled order sync
- When you manually sync products
- When the review widget or showcases are loaded onto your website pages

By using this plugin, you agree to the Reviewbird Terms and Conditions and Privacy Policy.

**Requirements:**

* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* A Reviewbird account on any plan, including the free plan (sign up at [reviewbird.com](https://reviewbird.com))

== Installation ==

**Automatic Installation:**

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "reviewbird"
4. Click **Install Now** and then **Activate**

**Manual Installation:**

1. Download the plugin ZIP file
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

**Configuration:**

Connection to your store is initiated through Reviewbird. Once you have the plugin activated, go to https://app.reviewbird.com/dashboard and click on "Connect".

After you have connected your store to Reviewbird, this is how you configure the plugin settings:

1. Go to **Reviewbird** in your admin menu
2. Click the toggle to enable the Reviewbird display widget
3. You can also enable the Google Schema JSON-LD output.
4. Settings are saved automatically.

== Frequently Asked Questions ==

= Do I need a Reviewbird account? =

Yes, you need a Reviewbird account to use this plugin. You can get started on the free plan at [reviewbird.com](https://reviewbird.com).

= Is Reviewbird for WooCommerce free? =

We have a free plan that lets you send 25 review requests a month, up to 500 orders. You can collect unlimited reviews. Advanced features require a paid plan.

= Can I migrate my reviews from Judge.me? =

Yes, we have a dedicated import tool to help you import reviews from Judge.me.

= Can I send review request emails to old clients?  =

You can send a bulk review request to all customers with orders within the last 90 days. Up to 5000 at a time.

= Is WooCommerce required? =

Yes, this plugin requires WooCommerce 5.0 or higher to function.

= How do I display reviews on my product pages? =

Once connected, enable the widget in **Reviewbird** in your admin menu. Reviews will automatically appear on your product pages.

= Can I customize the widget appearance? =

Yes, you can customize colors and styling through your [Reviewbird dashboard](https://app.reviewbird.com/dashboard).

== Screenshots ==

1. Reviewbird settings page
2. Review widget on product page
3. Review collection widget step 1
4. Review collection widget step 2
5. Review collection widget step 3
6. Review collection widget step 4
7. Review collection widget submission step
8. Review collection widget submitted
9. Review Wall showcase
10. Other showcase styles

== Source Code ==

The built JavaScript and CSS files in `assets/build/` are compiled from source files in `assets/src/` using the WordPress Scripts (`@wordpress/scripts`) build tool.

The full source code is available at: https://github.com/Objectivco/reviewbird-plugin

To build from source:

1. Clone the repository
2. Run `pnpm install`
3. Run `pnpm run build`

For development: `pnpm run dev`

== Changelog ==

= 1.1.0 =
New - Add Leave a review buttons to products in completed customer account orders, including CheckoutWC support.

= 1.0.24 =
New - Redesign the Get Started page for a clearer setup flow.
New - Prefill Reviewbird registration with the name and email address of the current WordPress user, site name, and public site URL.

= 1.0.23 =
Fix - Do not copy Reviewbird rating and schema metadata when a WooCommerce product is duplicated.

= 1.0.22 =
New - Include product tags and hierarchical categories in the products sync endpoint.
New - Report plugin version to Reviewbird via the X-Reviewbird-Version response header.
New - Add single product endpoint for on-demand widget product syncing.

= 1.0.21 =
Fix - Fix a couple of linting issues.
Fix - Fix min/tested versions.

= 1.0.20 =
New - Hook adjustments to make managing widget output locations easier.

= 1.0.19 =
New - Add new welcome screen.

= 1.0.18 =
Fix - Remove asset version from URLs to avoid caching issues.

= 1.0.17 =
Fix - Fix issue with min_rating parameter for API calls.

= 1.0.16 =
* Fix - Fix excessive health status checks with store ID is not set.

= 1.0.15 =
* New - Added [reviewbird_stars] shortcode.
* Fix - Prevent duplicate widget rendering when [reviewbird_widget] shortcode is used
* Fix - Fix issues with ReviewX review import

= 1.0.14 =
* Fix - Add support for ReviewX imports.
* Fix - Unschedule actions on deactivate.

= 1.0.13 =
* Fix - Fix URLs to include organization slug

= 1.0.12 =
* Compatibility updates for WordPress.org submission
