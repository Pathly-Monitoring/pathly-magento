<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\FlagManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\ZendEscaper;
use Pathly\Monitoring\Model\State;
use PHPUnit\Framework\TestCase;

/**
 * Shared doubles: a State kept in memory, block contexts, the object manager.
 */
trait Helper
{
    /** @var array Data saved by the in-memory State */
    protected array $flag = [];

    /**
     * Real State over an in-memory flag.
     *
     * @param array $data
     * @return State
     */
    protected function memoryState(array $data = []): State
    {
        $this->flag = $data;
        $flags = $this->createMock(FlagManager::class);
        $flags->method('getFlagData')->willReturnCallback(fn () => $this->flag === [] ? null : $this->flag);
        $flags->method('saveFlag')->willReturnCallback(function ($code, $value) {
            $this->flag = $value;
            return true;
        });
        $flags->method('deleteFlag')->willReturnCallback(function () {
            $this->flag = [];
            return true;
        });
        return new State($flags);
    }

    /**
     * Object manager for the few Magento constructors that ask it directly.
     *
     * @return void
     */
    protected function installObjectManager(): void
    {
        /** @var TestCase $this */
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(function (string $class) {
            if ($class === ZendEscaper::class) {
                return new ZendEscaper();
            }
            return $this->createMock($class);
        });
        ObjectManager::setInstance($objectManager);
    }

    /**
     * Context of a block, with a real escaper and a URL builder.
     *
     * @param string $contextClass
     * @param array $getters Extra getters of the context
     * @return object
     */
    protected function blockContext(string $contextClass, array $getters = []): object
    {
        $this->installObjectManager();
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn ($route, $params = []) => 'https://shop.example/' . $route . ($params ? '?' . http_build_query($params) : '')
        );
        $context = $this->createMock($contextClass);
        $getters += ['getEscaper' => new Escaper(), 'getUrlBuilder' => $url];
        foreach ($getters as $method => $value) {
            $context->method($method)->willReturn($value);
        }
        return $context;
    }
}
