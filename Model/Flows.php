<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

/**
 * Flows of the shop: those saved with Lumi from the module, and those typed in the settings.
 */
class Flows
{
    /**
     * @param State $state
     * @param Config $config
     */
    public function __construct(
        private readonly State $state,
        private readonly Config $config
    ) {
    }

    /**
     * Identifiers of every Flow of the shop.
     *
     * @return string[]
     */
    public function ids(): array
    {
        return array_values(array_unique(array_merge($this->saved(), $this->config->getExtraFlowIds())));
    }

    /**
     * Remember a Flow saved with Lumi.
     *
     * @param string $id
     * @return void
     */
    public function add(string $id): void
    {
        $ids = $this->saved();
        $ids[] = $id;
        $this->state->set(['flowIds' => array_values(array_unique($ids))]);
    }

    /**
     * Flows saved with Lumi.
     *
     * @return string[]
     */
    private function saved(): array
    {
        return array_values(array_filter((array) $this->state->get('flowIds', []), 'is_string'));
    }
}
