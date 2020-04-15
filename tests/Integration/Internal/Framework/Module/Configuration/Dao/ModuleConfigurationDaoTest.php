<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Framework\Module\Configuration\Dao;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ModuleConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Tests\TestUtils\Traits\ContainerTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModuleConfigurationDaoTest extends TestCase
{
    use ContainerTrait;

    public function setUp(): void
    {
        $this->setupIntegrationTest();
        $this->prepareProjectConfiguration();

        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->tearDownTestContainer();
        parent::tearDown();
    }

    public function testSaving()
    {
        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration
            ->setId('testId')
            ->setPath('somePath');

        $dao = $this->get(ModuleConfigurationDaoInterface::class);
        $dao->save($moduleConfiguration, 1);

        $this->assertEquals(
            $moduleConfiguration,
            $dao->get('testId', 1)
        );
    }

    private function prepareProjectConfiguration()
    {
        $this->get(ShopConfigurationDaoInterface::class)->save(
            new ShopConfiguration(),
            1
        );
    }
}
