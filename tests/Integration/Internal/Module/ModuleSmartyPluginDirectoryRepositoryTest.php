<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Core\Module;

use OxidEsales\Eshop\Core\FileCache;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleSmartyPluginDirectoryRepository;
use OxidEsales\Eshop\Core\Module\ModuleVariablesLocator;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ShopIdCalculator;
use OxidEsales\Eshop\Core\SubShopSpecificFileCache;
use OxidEsales\Eshop\Core\Module\ModuleSmartyPluginDirectories;
use OxidEsales\EshopCommunity\Tests\TestUtils\Traits\ContainerTrait;
use PHPUnit\Framework\TestCase;

class ModuleSmartyPluginDirectoryRepositoryTest extends TestCase
{
    use ContainerTrait;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->setupIntegrationTest();
        $this->forceDatabaseSetup();

    }

    public function tearDown(): void
    {
        $this->tearDownTestContainer();
        parent::tearDown();
    }

    public function testSaving()
    {
        $directories = oxNew(
            ModuleSmartyPluginDirectories::class,
            oxNew(Module::class)
        );
        $directories->add(['first', 'second'], 'moduleId');

        $repository = $this->getModuleSmartyPluginDirectoryRepository();
        $repository->save($directories);

        $this->assertEquals(
            $directories,
            $repository->get()
        );
    }

    private function getModuleSmartyPluginDirectoryRepository()
    {
        $moduleVariablesCache = oxNew(FileCache::class);
        $shopIdCalculator = oxNew(ShopIdCalculator::class, $moduleVariablesCache);

        $subShopSpecificCache = oxNew(
            SubShopSpecificFileCache::class,
            $shopIdCalculator
        );

        $moduleVariablesLocator = oxNew(
            ModuleVariablesLocator::class,
            $subShopSpecificCache,
            $shopIdCalculator
        );

        return oxNew(
            ModuleSmartyPluginDirectoryRepository::class,
            Registry::getConfig(),
            $moduleVariablesLocator,
            oxNew(Module::class)
        );
    }
}
