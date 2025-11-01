<?php
/**
 * Unified health checker for ReviewBird store status.
 *
 * @package reviewbird
 */

namespace reviewbird\Services;

use reviewbird\Api\Client;

class HealthChecker
{
    protected ?Client $apiClient = null;
    protected const CACHE_KEY = 'reviewbird_store_status';

    /**
     * Get or create API client (lazy loading).
     *
     * @return Client
     */
    protected function getApiClient(): Client
    {
        if (!$this->apiClient) {
            $this->apiClient = new Client();
        }
        return $this->apiClient;
    }

    /**
     * Get store status with smart caching.
     *
     * @param bool $skipCache Force fresh check.
     * @return array|null Status array or null if unavailable.
     */
    public function getStatus(bool $skipCache = false): ?array
    {
        if (!$skipCache) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $status = $this->fetchStatus();

        if ($status) {
            $this->cacheStatus($status);
        }

        return $status;
    }

    /**
     * Fetch fresh status from API.
     *
     * @return array|null
     */
    protected function fetchStatus(): ?array
    {
        $apiClient = $this->getApiClient();
        $storeId = $apiClient->get_store_id_for_frontend();

        if ($storeId) {
            // Use store ID endpoint
            $response = $apiClient->request("/api/stores/{$storeId}/status");
        } else {
            // Fallback to domain-based lookup
            $domain = parse_url(home_url(), PHP_URL_HOST) ?? '';
            $endpoint = '/api/woocommerce/health?domain=' . urlencode($domain);
            $response = $apiClient->request($endpoint);
        }

        if (is_wp_error($response)) {
            return null;
        }

        return $response;
    }

    /**
     * Cache status with TTL based on health.
     * Matches backend: 5 min when healthy, 30 sec when not.
     *
     * @param array $status Status array from API.
     */
    protected function cacheStatus(array $status): void
    {
        // Match backend TTL: 5 min when healthy/syncing, 30 sec otherwise
        $ttl = in_array($status['status'] ?? '', ['healthy', 'syncing']) ? 300 : 30;

        set_transient(self::CACHE_KEY, $status, $ttl);
    }

    /**
     * Clear cached status.
     */
    public static function clearCache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Check if status is healthy.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        $status = $this->getStatus();
        return $status && ($status['status'] ?? '') === 'healthy';
    }

    /**
     * Check if store has active subscription.
     *
     * @return bool
     */
    public function hasSubscription(): bool
    {
        $status = $this->getStatus();
        return $status && ($status['has_active_subscription'] ?? false);
    }

    /**
     * Check if widget can be shown.
     * Widget shows if healthy or syncing AND has subscription.
     *
     * @return bool
     */
    public function canShowWidget(): bool
    {
        $status = $this->getStatus();

        if (!$status) {
            return false;
        }

        // Widget can show if healthy or syncing AND has subscription
        return in_array($status['status'] ?? '', ['healthy', 'syncing'])
            && ($status['has_active_subscription'] ?? false);
    }
}
