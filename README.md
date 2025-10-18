# ReviewBop WordPress Plugin

Connect your WooCommerce store to ReviewBop for advanced review collection and display.

## Features

- **OAuth Integration**: Seamless connection to ReviewBop dashboard
- **Automatic Widget Injection**: Review widgets automatically appear on product pages  
- **Real-time Sync**: Products, reviews, and orders sync automatically to ReviewBop
- **Action Scheduler**: Reliable background processing using WooCommerce's Action Scheduler
- **Security First**: Store tokens never exposed to frontend, proper capability checks
- **Developer Friendly**: Extensive filter system for customization

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+  
- PHP 7.4+

## Installation

### From WordPress.org (Coming Soon)
1. Search for "ReviewBop Reviews" in your WordPress admin
2. Install and activate the plugin
3. Go to Settings > ReviewBop to connect your store

### Manual Installation
1. Download the plugin files
2. Upload to your `/wp-content/plugins/reviewbop-reviews/` directory
3. Activate the plugin through the WordPress admin
4. Go to Settings > ReviewBop to connect your store

## Getting Started

1. **Connect Your Store**: Click "Connect to ReviewBop" in Settings > ReviewBop
2. **Create ReviewBop Account**: If you don't have one, you'll be guided to create an account
3. **Authorize Connection**: Grant the plugin access to your ReviewBop store
4. **Widgets Appear Automatically**: Review widgets will automatically appear on all product pages

## Environment Configuration

The plugin automatically detects your environment and uses the appropriate ReviewBop servers:

- **Development**: `https://reviewbop.test` (for local development)
- **Staging**: `https://staging.reviewbop.com` (when ready)  
- **Production**: `https://app.reviewbop.com` (live site)

You can override this by defining a constant in your `wp-config.php`:

```php
// Force specific environment
define('REVIEWAPP_ENVIRONMENT', 'development'); // or 'staging', 'production'
```

## Developer Customization

The plugin uses opinionated defaults but provides extensive customization through filters:

### Disable Auto-Injection
```php
// Disable automatic widget injection
add_filter('reviewbop_auto_inject_widgets', '__return_false');
```

### Change Widget Placement
```php
// Change where widgets appear
add_filter('reviewbop_widget_hook', function() {
    return 'woocommerce_single_product_summary'; // Different hook
});

// Change widget priority  
add_filter('reviewbop_widget_priority', function() {
    return 15; // Earlier in the page
});
```

### Conditional Widget Display
```php
// Hide widgets for specific products
add_filter('reviewbop_show_widget_for_product', function($show, $product) {
    // Don't show on virtual/downloadable products
    return !$product->is_virtual() && !$product->is_downloadable();
}, 10, 2);
```

### Custom Widget Attributes
```php
// Add custom data attributes
add_filter('reviewbop_widget_attributes', function($attrs, $product) {
    $attrs['custom-category'] = $product->get_category_ids()[0] ?? '';
    return $attrs;
}, 10, 2);
```

### Complete Widget Customization
```php
// Replace widget HTML entirely
add_filter('reviewbop_widget_html', function($html, $product, $widget_id, $attrs) {
    return sprintf(
        '<div class="my-custom-reviews" id="%s" data-product="%s"></div>',
        esc_attr($widget_id),
        esc_attr($product->get_id())
    );
}, 10, 4);
```

## Action Scheduler Integration

The plugin uses WooCommerce's Action Scheduler for reliable background processing:

- **Product Sync**: Queued when products are created/updated
- **Review Sync**: Queued when reviews are created/updated/deleted  
- **Order Events**: Queued when orders are completed (for follow-up emails)
- **OAuth Processing**: Token exchange happens in background
- **Cleanup Tasks**: Automatic cleanup of expired OAuth states

## Security Features

- **Token Separation**: Store tokens (privileged) vs Store IDs (public)
- **Capability Checks**: All admin operations require `manage_options` capability  
- **Nonce Verification**: All forms protected with WordPress nonces
- **Input Sanitization**: All user input properly sanitized
- **Origin Validation**: Widget APIs validate request origins
- **OAuth State Verification**: Secure OAuth flow with state parameter validation

## Development

### Build Assets
```bash
npm install
npm run build    # Production build
npm run dev      # Development build
```

### Code Quality
```bash
composer install
composer run lint        # Check PHP code standards
composer run lint-fix    # Fix PHP code standards  
npm run lint:js          # Check JavaScript
npm run lint:css         # Check CSS
```

### Testing
```bash
composer run test        # PHPUnit tests
npm run test            # JavaScript tests  
```

## Troubleshooting

### Connection Issues
- Verify your environment constants are correct
- Check that your WordPress site can make outbound HTTPS requests
- Ensure Action Scheduler is functioning (WooCommerce > Status > Scheduled Actions)

### Widget Not Appearing  
- Verify connection status in Settings > ReviewBop
- Check that WooCommerce is active and you're on a product page
- Ensure the theme supports the `woocommerce_after_single_product_summary` hook

### Development Setup
- Set `REVIEWAPP_ENVIRONMENT` to `'development'` in wp-config.php
- Ensure your local ReviewBop instance is running at `https://reviewbop.test`

## Changelog

### 1.0.0
- Initial release
- OAuth integration with ReviewBop
- Automatic widget injection  
- Product/review/order synchronization
- Action Scheduler integration
- Comprehensive filter system

## Support

- **Documentation**: [ReviewBop Plugin Docs](https://docs.reviewbop.com/wordpress)  
- **Support**: [ReviewBop Support](https://reviewbop.com/support)
- **Issues**: [GitHub Issues](https://github.com/reviewbop/wordpress-plugin/issues)