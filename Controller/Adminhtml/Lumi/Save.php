<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Controller\Adminhtml\Lumi;

/**
 * Save the Flow validated by the administrator.
 */
class Save extends AbstractLumi
{
    /**
     * @inheritdoc
     */
    protected function handle(): array
    {
        $index = (int) $this->getRequest()->getParam('index');
        return ['flowId' => $this->lumi->save($this->trialId(), $index)];
    }
}
