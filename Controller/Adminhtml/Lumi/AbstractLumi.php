<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Controller\Adminhtml\Lumi;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Pathly\Monitoring\Model\Lumi;

/**
 * Base of the Lumi actions: POST only (form key checked by Magento), JSON answer.
 */
abstract class AbstractLumi extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Pathly_Monitoring::lumi';

    /**
     * @param Context $context
     * @param Lumi $lumi
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        protected readonly Lumi $lumi,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Run the action and answer in JSON.
     *
     * @return Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            return $result->setData(['ok' => true] + $this->handle());
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Work of the action.
     *
     * @return array
     * @throws LocalizedException
     */
    abstract protected function handle(): array;

    /**
     * Identifier of the trial sent by the page.
     *
     * @return string
     * @throws LocalizedException
     */
    protected function trialId(): string
    {
        $id = (string) $this->getRequest()->getParam('trial');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new LocalizedException(__('Unknown trial.'));
        }
        return $id;
    }
}
