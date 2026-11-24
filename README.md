# Reviewbird WordPress Plugin

Powerfully simple product review collection, moderation, and management for WooCommerce.

Learn more at [reviewbird.com](https://reviewbird.com).

Current version: **1.3.5**. See [readme.txt](readme.txt) for release notes.

Run `composer lint`, `composer test`, `pnpm run lint:js`, `pnpm run lint:css`, `pnpm test --runInBand --watch=false`, and `pnpm run build` before release.

Build the release ZIP with `pnpm run plugin-zip`.

The integration checks require a disposable WordPress site with WooCommerce and this plugin active. Use a database named `reviewbird_scheduler_test_*`. Disable WP-Cron, the Action Scheduler async runner, and external HTTP requests. These checks change and delete test data:

```sh
wp --path=/path/to/test-site action-scheduler migrate
php tests/SchedulerIntegrationCheck.php /path/to/test-site
php tests/ClassIntegrationCheck.php /path/to/test-site
wp --path=/path/to/test-site eval-file tests/PluginBootstrapCheck.php
wp --path=/path/to/test-site --skip-plugins=woocommerce eval-file tests/PluginBootstrapCheck.php
```

Run the scheduler check on a disposable multisite network to test network deactivation.

PHP checks use WordPress standards with PSR-4 class filenames for Composer autoloading.
