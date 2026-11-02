<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Controller\Adminhtml\Lumi;

/**
 * Progress of a trial of Lumi.
 */
class Status extends AbstractLumi
{
    /**
     * @inheritdoc
     */
    protected function handle(): array
    {
        return $this->lumi->status($this->trialId());
    }
}
