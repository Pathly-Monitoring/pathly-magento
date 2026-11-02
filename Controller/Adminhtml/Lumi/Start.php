<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Controller\Adminhtml\Lumi;

use Magento\Framework\Exception\LocalizedException;

/**
 * Start a trial of Lumi on the shop, after the administrator confirmed managing it.
 */
class Start extends AbstractLumi
{
    /**
     * @inheritdoc
     */
    protected function handle(): array
    {
        if ((string) $this->getRequest()->getParam('confirm_owner') !== '1') {
            throw new LocalizedException(__('Confirm that you manage this shop.'));
        }
        return $this->lumi->start();
    }
}
