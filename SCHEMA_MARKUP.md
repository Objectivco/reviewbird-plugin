# ReviewBop Schema Markup Feature

## Overview

The ReviewBop WordPress plugin automatically adds Google-compliant structured data (JSON-LD schema) to WooCommerce product pages. This enables rich snippets in search results, displaying star ratings, review counts, and other product information directly in Google search results.

## Features

- **Automatic Schema Generation**: Generates Product schema with AggregateRating and Review objects
- **SEO Optimized**: Follows Google's structured data guidelines for maximum compatibility
- **Smart Caching**: Caches review data for 4 hours to reduce API calls
- **Cache Invalidation**: Automatically clears cache when ratings are updated
- **Flexible Brand Detection**: Supports multiple brand taxonomies and attributes
- **Stock Status Mapping**: Maps WooCommerce stock status to Schema.org availability
- **Dashboard Setting**: Enable/disable via plugin settings (enabled by default)
- **Developer-Friendly**: Includes filters for customization

## Schema Output

The plugin outputs JSON-LD structured data in the `<head>` section of product pages:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Product Name",
  "description": "Product description",
  "image": "https://example.com/product-image.jpg",
  "sku": "PROD-123",
  "brand": {
    "@type": "Brand",
    "name": "Brand Name"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/product",
    "price": "99.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.5",
    "reviewCount": "250",
    "bestRating": "5",
    "worstRating": "1"
  },
  "review": [
    {
      "@type": "Review",
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": "5",
        "bestRating": "5",
        "worstRating": "1"
      },
      "author": {
        "@type": "Person",
        "name": "John Doe"
      },
      "reviewBody": "Great product!",
      "headline": "Excellent purchase",
      "datePublished": "2025-01-15"
    }
  ]
}
```

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- ReviewBop plugin connected to a ReviewBop store
- Products must have reviews in ReviewBop

## Configuration

### Enable/Disable Schema Markup

By default, schema markup is enabled. You can enable or disable it in two ways:

**Via Plugin Dashboard (Recommended):**
1. Go to WordPress Admin > Settings > ReviewBop (`/wp-admin/options-general.php?page=reviewbop-settings`)
2. Scroll to the "SEO Schema Markup" section
3. Toggle the switch to enable or disable schema markup
4. The setting is saved automatically

**Via Code:**
To programmatically disable it, add this to your theme's `functions.php`:

```php
add_filter( 'reviewbop_enable_schema', '__return_false' );
```

Or set the WordPress option:

```php
update_option( 'reviewbop_enable_schema', 'no' );
```

### Customize Schema Output

You can modify the schema data before output using the `reviewbop_product_schema` filter:

```php
add_filter( 'reviewbop_product_schema', function( $schema, $product_id ) {
    // Add custom properties
    $schema['additionalProperty'] = 'value';

    // Modify existing properties
    $schema['brand']['name'] = 'Custom Brand';

    return $schema;
}, 10, 2 );
```

### Brand Detection

The plugin attempts to detect product brands from multiple sources:

1. **Brand Taxonomies**: `product_brand`, `pwb-brand`, `yith_product_brand`
2. **Product Attributes**: `brand` attribute
3. **Post Meta**: `_vendor` meta field

To add custom brand detection:

```php
add_filter( 'reviewbop_product_schema', function( $schema, $product_id ) {
    $product = wc_get_product( $product_id );

    // Get brand from custom source
    $custom_brand = get_post_meta( $product_id, '_custom_brand', true );

    if ( $custom_brand ) {
        $schema['brand'] = array(
            '@type' => 'Brand',
            'name'  => $custom_brand,
        );
    }

    return $schema;
}, 10, 2 );
```

## Caching

### Cache Duration

Review data is cached for 4 hours (14400 seconds) using WordPress transients. The cache key format is:

```
reviewbop_schema_reviews_{product_id}
```

### Cache Invalidation

Cache is automatically cleared when:

1. Product ratings are updated via the ReviewBop webhook
2. Manual invalidation using the `clear_schema_cache()` method

### Manual Cache Clearing

To manually clear the schema cache for a product:

```php
$schema_markup = new \ReviewBop\Integration\SchemaMarkup();
$schema_markup->clear_schema_cache( $product_id );
```

Or clear all schema caches:

```php
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_reviewbop_schema_reviews_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_reviewbop_schema_reviews_%'" );
```

## Stock Status Mapping

WooCommerce stock status is mapped to Schema.org availability:

| WooCommerce Status | Schema.org Availability |
|-------------------|-------------------------|
| In Stock | `https://schema.org/InStock` |
| Out of Stock | `https://schema.org/OutOfStock` |
| On Backorder | `https://schema.org/PreOrder` |
| Low Stock | `https://schema.org/LimitedAvailability` |

## Testing Schema Markup

### Google Rich Results Test

1. Visit a product page on your site
2. Copy the page URL
3. Go to [Google Rich Results Test](https://search.google.com/test/rich-results)
4. Paste the URL and click "Test URL"
5. Verify that the Product schema is detected and valid

### View Source

1. Visit a product page on your site
2. Right-click and select "View Page Source"
3. Search for `<script type="application/ld+json">`
4. Verify the JSON-LD output is present and properly formatted

### Schema Markup Validator

1. Visit [Schema.org Validator](https://validator.schema.org/)
2. Paste your product page URL
3. Verify no errors are reported

## Troubleshooting

### Schema Not Appearing

1. **Check if WooCommerce is active**: Schema only outputs on WooCommerce product pages
2. **Verify product page**: Ensure you're viewing a single product page, not a shop page
3. **Check option**: Verify `reviewbop_enable_schema` option is set to 'yes'
4. **Theme compatibility**: Some themes may remove `wp_head` hook

### No Reviews in Schema

1. **Check API connection**: Ensure ReviewBop store is connected
2. **Verify reviews exist**: Product must have approved reviews in ReviewBop
3. **Check cache**: Clear schema cache and reload the page
4. **API timeout**: If API is slow, increase timeout in `SchemaMarkup.php`

### Invalid Schema Errors

1. **Missing required fields**: Ensure products have name, image, and price
2. **Invalid dates**: Verify review dates are in valid ISO format
3. **Rating out of range**: Reviews must have ratings between 1-5

## Performance Considerations

- **Caching**: 4-hour cache significantly reduces API calls
- **Review Limit**: Only first 10 reviews are included (Google recommendation)
- **Lazy Loading**: Schema is generated on-demand, not pre-generated
- **API Timeout**: 10-second timeout for API requests to prevent page slowdown

## SEO Benefits

- **Rich Snippets**: Star ratings displayed in search results
- **Higher CTR**: Rich snippets can increase click-through rates by 30%
- **Trust Signals**: Reviews and ratings build consumer trust
- **SERP Real Estate**: Schema markup can increase visibility in search results

## Developer Hooks

### Actions

```php
// Before schema is output
do_action( 'reviewbop_before_schema_output', $product_id );

// After schema is output
do_action( 'reviewbop_after_schema_output', $product_id );
```

### Filters

```php
// Modify entire schema
apply_filters( 'reviewbop_product_schema', $schema, $product_id );

// Modify cache duration (in seconds, default is 4 hours)
apply_filters( 'reviewbop_schema_cache_duration', 4 * HOUR_IN_SECONDS );

// Modify number of reviews to include
apply_filters( 'reviewbop_schema_review_limit', 10 );
```

## Support

For issues or questions about schema markup:

1. Check [Google's Structured Data Guidelines](https://developers.google.com/search/docs/appearance/structured-data/product)
2. Test with [Google Rich Results Test](https://search.google.com/test/rich-results)
3. Contact ReviewBop support for API-related issues
4. Check WordPress error logs for PHP errors

## Version History

- **1.0.0**: Initial schema markup implementation
  - Product schema with offers
  - AggregateRating support
  - Individual review markup
  - Automatic caching
  - Brand detection
  - Stock status mapping
