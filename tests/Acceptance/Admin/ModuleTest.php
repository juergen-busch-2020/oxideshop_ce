<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Acceptance\Admin;

/**
 * Admin interface functionality.
 *
 * @group module
 */
class ModuleTest extends ModuleBaseTest
{
    public function setUp()
    {
        parent::setUp();

        $this->installModule('test1');
        $this->installModule('test2');
        $this->installModule('oxid/test6');
        $this->installModule('oxid/test7');
        $this->installModule('oxid/namespace1');
        $this->installModule('oxid/InvalidNamespaceModule1');
    }

    /**
     * Testing modules in vendor directory. Checking when any file with source code class of module is deleted.
     *
     * @group adminFunctionality
     */
    public function testModulesHandlingExtendingClass()
    {
        $testConfig = $this->getTestConfig();
        if ($testConfig->isSubShop()) {
            $this->markTestSkipped("Test is not for SubShop");
        }

        $this->loginAdmin("Extensions", "Modules");

        $this->activateModule("Test module #1");
        $this->activateModule("Test module #2");
        $this->activateModule("Test module #7");
        $this->activateModule("Namespaced module #1");

        //checking if module all entry is displayed
        $this->openTab("Installed Shop Modules");
        $this->assertTextPresent("Drag items to change modules order. After changing order press Save button to save current modules order.");
        $childClassesContainerId = 'OxidEsales---Eshop---Application---Controller---ContentController';
        $namespacedModuleClassElementLocator = "//li[@id='$childClassesContainerId']//li[@id='OxidEsales\\EshopCommunity\\Tests\\Acceptance\\Admin\\testData\\modules\\oxid\\namespace1\\Controllers\\ContentController']/span";
        $this->assertEquals(
            'OxidEsales\EshopCommunity\Tests\Acceptance\Admin\testData\modules\oxid\namespace1\Controllers\ContentController',
            $this->getText($namespacedModuleClassElementLocator)
        );

        $test1ModuleClassElementLocator = "//li[@id='$childClassesContainerId']//li[@id='test1/controllers/test1content']/span";
        $this->assertEquals('test1/controllers/test1content', $this->getText($test1ModuleClassElementLocator));
        $test2ModuleClassElementLocator = "//li[@id='$childClassesContainerId']//li[@id='test2/view/myinfo2']/span";
        $this->assertEquals('test2/view/myinfo2', $this->getText($test2ModuleClassElementLocator));
        $test7ModuleClassElementLocator = "//li[@id='$childClassesContainerId']//li[@id='oxid/test7/view/myinfo7']/span";
        $this->assertEquals('oxid/test7/view/myinfo7', $this->getText($test7ModuleClassElementLocator));

        $this->open(shopURL."?cl=content&fnc=showContent");
        $this->assertTextPresent(' + namespace1');

        $this->clearCache();
        $this->openShop();
        $this->open(shopURL . "en/About-Us/");
        $this->assertTextPresent("About Us + info1 + info2 + info7 + namespace1");

        $this->deleteModuleClass();

        $this->loginAdmin("Extensions", "Modules");
        $this->frame("edit");
        $this->assertTextPresent("Problematic Files");
        $this->assertTextPresent('test1/controllers/test1content');
        $this->clickAndWait("yesButton");

        $this->clearCache();
        $this->openShop();
        $this->open(shopURL . "en/About-Us/");
        $this->assertTextPresent("About Us + info2 + info7 + namespace1");
        $this->assertTextNotPresent("About Us + info1 + info2 + info7 + namespace1");
    }

    /**
     * @covers \OxidEsales\EshopCommunity\Core\Module\ModuleList::_getInvalidExtensions()
     */
    public function testGetDeletedExtensionsForNamespaceModuleShowErrorForNonLoadableClasses()
    {
        $testConfig = $this->getTestConfig();
        if ($testConfig->isSubShop()) {
            $this->markTestSkipped('Test is not for SubShop');
        }

        $this->loginAdmin('Extensions', 'Modules');
        /** The metadata.php of module 'Invalid Namespaced Module #1' refers to 2 non loadable classes */
        $this->activateModule('Invalid Namespaced Module #1');

        //checking if all expected non loadable classes are displayed
        $this->openTab("Installed Shop Modules");
        $this->assertTextPresent("Problematic Files");
        $this->assertTextPresent('NonExistentFile', 'The class name of a non existing class file should be listed in the section "Problematic Files"');

        //checking if clicking "Yes" fixes the problematic files
        $this->clickAndWait("yesButton");
        $this->openTab("Installed Shop Modules");
        $this->assertTextNotPresent("Problematic Files");
    }

    /**
     * Testing modules in vendor directory. Checking when any file with source code class of module is deleted.
     *
     * @group adminFunctionality
     */
    public function testModulesHandlingDeleteFile()
    {
        $testConfig = $this->getTestConfig();
        if ($testConfig->isSubShop()) {
            $this->markTestSkipped("Test is not for SubShop");
        }

        $this->loginAdmin("Extensions", "Modules");
        $this->clickAndWait("link=Test module #6 (in vendor dir)");
        $this->frame("edit");
        $this->clickAndWait("//form[@id='myedit']//input[@value='Activate']");
        $this->assertElementPresent("//form[@id='myedit']//input[@value='Deactivate']");
        $this->frame('list');
        $this->waitForElement("link=Test tab EN");
        $this->openTab("Test tab EN");
        $this->clearCache();
        $this->openShop();
        $this->open(shopURL."en/About-Us/");
        $this->assertTextPresent("About Us + info6");

        $moduleDirectoryPath= $this->getTestConfig()->getShopPath() . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR ;

        rename(
            $moduleDirectoryPath . 'oxid/test6/view/myinfo6.php',
            $moduleDirectoryPath . 'oxid/test6/view/deleted'
        );

        $this->openShop();
        $this->open(shopURL."en/About-Us/");
        $this->assertTextNotPresent("About Us + info6");
        $this->assertTextPresent("About Us");

        // Check if error logged
        $this->assertLoggedException(\OxidEsales\Eshop\Core\Exception\SystemComponentException::class, "Module ID (module id not availible)");

        //checking if module is deactivated after /dir rename
        $this->loginAdmin("Extensions", "Modules");
        $this->frame("edit");
        $this->assertTextPresent("Invalid modules were detected");
        $this->assertTextPresent("Do you want to delete all registered module information and saved configurations");
        $this->clickAndWait("yesButton");
        $this->frame("list");
        $this->clickAndWait("link=Test module #6 (in vendor dir)");
        $this->frame("edit");
        $this->assertElementNotPresent("//form[@id='myedit']//input[@value='Deactivate']");
        $this->assertTextNotPresent("Invalid modules were detected");

        //checking if module (oxblock, menu.xml) is disabled in shop after vendor dir rename
        //NOTE: we need functionality to clean the tmp folder before reimplementing he check for oxblock
        $this->clearCache();
        $this->openShop();
        $this->clickAndWait("link=%HOME%");

        $this->assertTextNotPresent("Module #6 title EN");
        $this->switchLanguage("Deutsch");
        $this->assertTextNotPresent("Module #6 title DE");
        $this->clearCache();
        $this->openShop();
        $this->open(shopURL."en/About-Us/");
        $this->assertTextNotPresent("About Us + info6");

        rename(
            $moduleDirectoryPath . 'oxid/test6/view/deleted',
            $moduleDirectoryPath . 'oxid/test6/view/myinfo6.php'
        );

        $this->loginAdmin("Extensions", "Modules");
        $this->clickAndWait("link=Test module #6 (in vendor dir)");
        $this->clickAndWait("link=Test module #6 (in vendor dir)");
        $this->frame("edit");
        $this->clickAndWait("//form[@id='myedit']//input[@value='Activate']");
        $this->assertElementPresent("//form[@id='myedit']//input[@value='Deactivate']");
        $this->frame('list');
        $this->waitForElement("link=Test tab EN");
        $this->openTab("Test tab EN");
        $this->assertTextPresent("oxid/test6/view/myinfo6");
    }
}
