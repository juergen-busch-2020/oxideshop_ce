<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Core\Module;

use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;
use OxidEsales\EshopCommunity\Tests\TestUtils\IntegrationTestCase;
use OxidEsales\EshopCommunity\Tests\TestUtils\Traits\ModuleTestingTrait;
use Webmozart\PathUtil\Path;

/**
 * @internal
 */
class ModuleListTest extends IntegrationTestCase
{
    use ModuleTestingTrait;

    private $fixturePath;

    public function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = Path::canonicalize(Path::join(__DIR__, 'Fixtures'));
    }

    public function testDisabledModules()
    {
        $this->installModule('with_metadata_v21', $this->fixturePath);
        $this->installModule('with_class_extensions', $this->fixturePath);

        $this->assertSame(
            [
                'with_metadata_v21',
                'with_class_extensions',
            ],
            oxNew(ModuleList::class)->getDisabledModules()
        );
    }

    public function testDisabledModulesInfo()
    {
        $activeModuleId = 'with_metadata_v21';
        $this->installModule($activeModuleId, $this->fixturePath);
        $this->activateModule($activeModuleId);

        $notActiveModuleId = 'with_class_extensions';
        $this->installModule($notActiveModuleId, $this->fixturePath);

        $this->assertSame(
            ['with_class_extensions' => 'oeTest/with_class_extensions'],
            oxNew(ModuleList::class)->getDisabledModuleInfo()
        );
    }

    public function testDisabledModulesInfoWithNoModules()
    {
        $this->assertSame(
            [],
            oxNew(ModuleList::class)->getDisabledModuleInfo()
        );
    }

    public function testGetDisabledModuleClasses()
    {
        $notActiveModuleId = 'with_class_extensions';
        $this->installModule($notActiveModuleId, $this->fixturePath);

        $this->assertSame(
            [
                'with_class_extensions/ModuleArticle',
            ],
            oxNew(ModuleList::class)->getDisabledModuleClasses()
        );
    }

    public function testCleanup()
    {
        $activeModuleId = 'with_metadata_v21';
        $this->installModule($activeModuleId, $this->fixturePath);
        $this->activateModule($activeModuleId);

        $moduleList = $this
            ->getMockBuilder(ModuleList::class)
            ->setMethods(['getDeletedExtensions'])
            ->getMock();

        $moduleList
            ->method('getDeletedExtensions')
            ->willReturn(
                [
                    'with_metadata_v21' => 'someExtension',
                ]
            );
        $moduleList->cleanup();

        $moduleActivationBridge = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleActivationBridgeInterface::class);

        $this->assertFalse(
            $moduleActivationBridge->isActive('with_metadata_v21', 1)
        );
    }

    public function testModuleIds()
    {
        $this->installModule('with_metadata_v21', $this->fixturePath);
        $this->installModule('with_class_extensions', $this->fixturePath);

        $this->assertSame(
            [
                'with_metadata_v21',
                'with_class_extensions',
            ],
            oxNew(ModuleList::class)->getModuleIds()
        );
    }

    public function testGetDeletedExtensionsForModuleWithNoMetadata()
    {
        $container = ContainerFactory::getInstance()->getContainer();

        $shopConfigurationDao = $container->get(ShopConfigurationDaoBridgeInterface::class);
        $shopConfiguration = $shopConfigurationDao->get();

        $moduleWhichHasNoMetadata = new ModuleConfiguration();
        $moduleWhichHasNoMetadata
            ->setId('moduleWhichHasNoMetadata')
            ->setPath('moduleWhichHasNoMetadata');

        $shopConfiguration->addModuleConfiguration($moduleWhichHasNoMetadata);
        $shopConfigurationDao->save($shopConfiguration);

        $container->get(ModuleActivationBridgeInterface::class)->activate(
            'moduleWhichHasNoMetadata',
            Registry::getConfig()->getShopId()
        );

        $moduleExtensions = [
            Article::class => 'moduleWhichHasNoMetadata/anyExtension',
        ];

        Registry::getConfig()->setConfigParam('aModules', $moduleExtensions);

        $expectedDeletedExtensions = array(
            'moduleWhichHasNoMetadata' => array(
                'files' => array('moduleWhichHasNoMetadata/metadata.php')
            ),
        );

        $this->assertEquals(
            $expectedDeletedExtensions,
            oxNew(ModuleList::class)->getDeletedExtensions()
        );
    }

    public function testGetDeletedExtensionsWithMissingExtensions()
    {
        $this->markTestSkipped("MK: This failure is really strange. Check later.");
        $moduleId = 'InvalidNamespaceModule';
        $this->installModule($moduleId, $this->fixturePath);
        $this->activateModule($moduleId);

        $module = oxNew(Module::class);
        $module->load($moduleId);

        $this->assertSame(
            [
                $moduleId => [
                    'extensions' => [
                        'OxidEsales\Eshop\Application\Model\Article' =>
                            ['OxidEsales\EshopCommunity\Tests\Integration\Core\Module\Fixtures\InvalidNamespaceModule\Controller\NonExistentFile'],
                    ]
                ],
            ],
            oxNew(ModuleList::class)->getDeletedExtensions()
        );
    }

    public function testGetModulesWithExtendedClass()
    {
        $this->installModule('with_class_extensions', $this->fixturePath);
        $this->installModule('with_class_extensions2', $this->fixturePath);
        $this->activateModule('with_class_extensions');
        $this->activateModule('with_class_extensions2');

        $this->assertEquals(
            [
                'OxidEsales\Eshop\Application\Controller\ContentController' => ['OxidEsales\EshopCommunity\Tests\Integration\Core\Module\Fixtures\with_class_extenstions2\Controllers\ContentController'],
                'OxidEsales\Eshop\Application\Model\Article'         => ['with_class_extensions/ModuleArticle'],
            ],
            oxNew(ModuleList::class)->getModulesWithExtendedClass()
        );
    }

    public function testExtractModulePaths()
    {
        $this->installModule('with_class_extensions', $this->fixturePath);

        $this->assertEquals(
            [
                'with_class_extensions' => 'with_class_extensions'
            ],
            oxNew(ModuleList::class)->extractModulePaths()
        );
    }

    public function testGetModuleExtensionsWithMultipleExtensions()
    {
        $extensions = [
            'OxidEsales\Eshop\Application\Model\Article' => [
                'with_multiple_extensions/articleExtension1',
                'with_multiple_extensions/articleExtension2',
                'with_multiple_extensions/articleExtension3',
            ],
            'OxidEsales\Eshop\Application\Model\Order'   => [
                'with_multiple_extensions/oxOrder'
            ],
            'OxidEsales\Eshop\Application\Model\Basket'  => [
                'with_multiple_extensions/basketExtension'
            ]
        ];

        $this->installModule('with_multiple_extensions', $this->fixturePath);
        $this->activateModule('with_multiple_extensions');

        $this->assertSame($extensions, oxNew(ModuleList::class)->getModuleExtensions('with_multiple_extensions'));
    }

    public function testGetModuleExtensionsWithNoExtensions()
    {
        $this->installModule('with_metadata_v21', $this->fixturePath);
        $this->assertSame([], oxNew(ModuleList::class)->getModuleExtensions('with_metadata_v21'));
    }

    public function testGetModules()
    {
        $extensions = [
            'OxidEsales\Eshop\Application\Model\Article' => 'with_multiple_extensions/articleExtension1&with_multiple_extensions/articleExtension2&with_multiple_extensions/articleExtension3',
            'OxidEsales\Eshop\Application\Model\Order'   => 'with_multiple_extensions/oxOrder',
            'OxidEsales\Eshop\Application\Model\Basket'  => 'with_multiple_extensions/basketExtension',
            'OxidEsales\Eshop\Application\Controller\ContentController' => 'OxidEsales\EshopCommunity\Tests\Integration\Core\Module\Fixtures\with_class_extenstions2\Controllers\ContentController'
        ];

        $this->installModule('with_multiple_extensions', $this->fixturePath);
        $this->installModule('with_class_extensions2', $this->fixturePath);

        $this->assertSame($extensions, oxNew(ModuleList::class)->getModules());
    }
}
