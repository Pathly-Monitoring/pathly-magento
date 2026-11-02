<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Pathly\Monitoring\Model\Connection;

/**
 * API key field: checked with Pathly, then stored encrypted by Magento.
 *
 * The saved key is never shown again: the field displays asterisks. Once
 * saved, the Ping of the shop is attached or created.
 */
class ApiKey extends Encrypted
{
    /** @var string|null Key being saved, kept for afterSave() */
    private ?string $newKey = null;

    /** @var array Account of the key being saved */
    private array $account = [];

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     * @param Connection $connection
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor,
        private readonly Connection $connection,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $encryptor,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Check a new key with Pathly, then let Magento encrypt it.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        $this->newKey = null;
        if ($value !== '' && !preg_match('/^\*+$/', $value)) {
            $this->account = $this->connection->validate($value);
            $this->newKey = $value;
            $this->setValue($value);
        }
        parent::beforeSave();
        return $this;
    }

    /**
     * Attach the Ping with a new key, or forget the account when the key is removed.
     *
     * @return $this
     */
    public function afterSave()
    {
        parent::afterSave();
        if ($this->newKey !== null) {
            $this->connection->connect($this->newKey, $this->account);
        } elseif (trim((string) $this->getValue()) === '') {
            $this->connection->disconnect();
        }
        $this->newKey = null;
        return $this;
    }
}
