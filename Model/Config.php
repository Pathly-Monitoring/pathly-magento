<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Settings of the module (Stores > Configuration > Pathly), default scope only.
 */
class Config
{
    public const PATH_API_KEY = 'pathly_monitoring/connection/api_key';
    public const PATH_REPLAY = 'pathly_monitoring/flow/replay_after_update';
    public const PATH_EXTRA_FLOWS = 'pathly_monitoring/flow/extra_flow_ids';
    public const PATH_BADGE = 'pathly_monitoring/badge/enabled';
    public const PATH_BADGE_FOOTER = 'pathly_monitoring/badge/footer';

    /** Where a new user creates a free account, with the source of the sign-up. */
    public const SIGNUP_URL = 'https://pathlyhq.com/fr/login?mode=signup&ref=magento'
        . '&utm_source=magento&utm_medium=module';

    /** Web app of Pathly. */
    public const APP_URL = 'https://pathlyhq.com';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Decrypted API key, empty when the module is not connected.
     *
     * @return string
     */
    public function getApiKey(): string
    {
        $stored = (string) $this->scopeConfig->getValue(self::PATH_API_KEY);
        return $stored === '' ? '' : trim((string) $this->encryptor->decrypt($stored));
    }

    /**
     * Whether an API key is stored. Nothing is sent to Pathly before.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->getApiKey() !== '';
    }

    /**
     * Whether Flows are replayed after an update of Magento or of a module.
     *
     * @return bool
     */
    public function isReplayEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_REPLAY);
    }

    /**
     * Other Flows to replay, typed by the administrator (identifiers only).
     *
     * @return string[]
     */
    public function getExtraFlowIds(): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::PATH_EXTRA_FLOWS);
        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $id) {
            if (preg_match(self::UUID, $id)) {
                $ids[] = strtolower($id);
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Whether the availability badge is on. Off by default.
     *
     * @return bool
     */
    public function isBadgeEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_BADGE);
    }

    /**
     * Address of the shop: secure base URL of the default store view.
     *
     * @return string
     */
    public function getStoreUrl(): string
    {
        $store = $this->storeManager->getDefaultStoreView();
        $url = $store === null ? '' : (string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        return $url === '' ? (string) $this->scopeConfig->getValue('web/secure/base_url') : $url;
    }
}
