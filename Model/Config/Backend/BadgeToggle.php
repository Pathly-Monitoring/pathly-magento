<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Model\ErrorMessage;

/**
 * Badge switch: publishes the status page when turned on.
 */
class BadgeToggle extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Badge $badge
     * @param ErrorMessage $errors
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Badge $badge,
        private readonly ErrorMessage $errors,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Publish or hide, when the switch changed.
     *
     * @return $this
     * @throws LocalizedException When Pathly refuses: the switch is not saved
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            try {
                if ((string) $this->getValue() === '1') {
                    $this->badge->enable();
                } else {
                    $this->badge->disable();
                }
            } catch (ApiException $e) {
                throw new LocalizedException(__('Badge: %1', $this->errors->from($e)));
            }
        }
        return parent::afterSave();
    }
}
