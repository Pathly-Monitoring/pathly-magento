<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\FlagManager;

/**
 * What the module remembers between two runs, in one row of the flag table.
 *
 * Identifiers of the Ping and the Flows, fingerprint of the installed
 * versions, last replay, status page. Never the API key: only a short hash
 * of it, to notice that the key changed.
 */
class State
{
    public const FLAG_CODE = 'pathly_monitoring_state';

    /** @var array|null */
    private ?array $data = null;

    /**
     * @param FlagManager $flagManager
     */
    public function __construct(private readonly FlagManager $flagManager)
    {
    }

    /**
     * One value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Every value.
     *
     * @return array
     */
    public function all(): array
    {
        if ($this->data === null) {
            $stored = $this->flagManager->getFlagData(self::FLAG_CODE);
            $this->data = is_array($stored) ? $stored : [];
        }
        return $this->data;
    }

    /**
     * Change some values and save. A null value removes the key.
     *
     * @param array $values
     * @return void
     */
    public function set(array $values): void
    {
        $data = $this->all();
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = $value;
        }
        $this->data = $data;
        $this->flagManager->saveFlag(self::FLAG_CODE, $data);
    }

    /**
     * Forget everything.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->data = [];
        $this->flagManager->deleteFlag(self::FLAG_CODE);
    }

    /**
     * Forget the cached copy: the next read comes from the database.
     *
     * @return void
     */
    public function reload(): void
    {
        $this->data = null;
    }
}
