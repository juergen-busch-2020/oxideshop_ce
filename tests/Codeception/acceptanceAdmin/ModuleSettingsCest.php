<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\CodeceptionAdmin;

use Codeception\Util\Fixtures;
use OxidEsales\EshopCommunity\Tests\Codeception\AcceptanceAdminTester;

final class ModuleSettingsCest
{
    private const CODECEPTION_TEST_MODULE_1 = 'codeception/test-module-1';

    /** @param AcceptanceAdminTester $I */
    public function moduleSettingsForm(AcceptanceAdminTester $I): void
    {
        $I->installModule(__DIR__ . '../_data/modules/codeception/test-module-1');
        $I->activateModule(self::CODECEPTION_TEST_MODULE_1);

        $I->wantToTest('module settings are loaded from metadata and form save works');

        $userData = $this->getAdminUserData();

        $loginPage = $I->openAdmin();
        $loginPage->login($userData['userLoginName'], $userData['userPassword']);

        $moduleList = $loginPage->openModules();
        $module = $moduleList->selectModule('Codeception test module #1');
        $module->openModuleTab('Settings');

        $I->click($I->see('Empty settings group'));
        $this->checkEmptyInitialSettingsLoaded($I);

        $I->click($I->see('Filled Settings Group'));
        $this->checkFilledInitialSettingsLoaded($I);

        $I->click($I->see('Empty settings group'));
        $this->modifyEmptyInitialSettings($I);
        $I->click('save');
        $this->checkModifiedSettingsNotEmpty($I);

        $I->uninstallModule(self::CODECEPTION_TEST_MODULE_1);
    }

    private function getAdminUserData(): array
    {
        return Fixtures::get('adminUser');
    }

    /** @param AcceptanceAdminTester $I */
    private function checkEmptyInitialSettingsLoaded(AcceptanceAdminTester $I): void
    {
        $I->dontSeeCheckboxIsChecked('confbools[testEmptyBoolConfig]');
        $I->canSeeInField('confstrs[testEmptyStrConfig]', '');
        $I->canSeeInField('confarrs[testEmptyArrConfig]', '');
        $I->canSeeInField('confaarrs[testEmptyAArrConfig]', '');
        $I->canSeeInField('confselects[testEmptySelectConfig]', 0);
        $I->canSeeInField('confpassword[testEmptyPasswordConfig]', '');
    }

    /** @param AcceptanceAdminTester $I */
    private function checkFilledInitialSettingsLoaded(AcceptanceAdminTester $I): void
    {
        $I->seeCheckboxIsChecked('confbools[testFilledBoolConfig]');
        $I->canSeeInField('confstrs[testFilledStrConfig]', 'testStr');
        $I->canSeeInField('confarrs[testFilledArrConfig]', "option1\noption2");
        $I->canSeeInField('confaarrs[testFilledAArrConfig]', "key1 => option1\nkey2 => option2");
        $I->canSeeInField('confselects[testFilledSelectConfig]', 2);
        $I->dontSee('confpassword[testFilledPasswordConfig]');
        $I->canSeeInField('confpassword[testFilledPasswordConfig]', '');
    }

    /** @param AcceptanceAdminTester $I */
    private function modifyEmptyInitialSettings(AcceptanceAdminTester $I): void
    {
        $I->checkOption('confbools[testEmptyBoolConfig]');
        $I->fillField('confstrs[testEmptyStrConfig]', 'new-string');
        $I->fillField('confarrs[testEmptyArrConfig]', "new-option-1\nnew-option-2");
        $I->fillField('confaarrs[testEmptyAArrConfig]', "key1 => new-option-1\nkey2 => new-option-2");
        $I->selectOption('confselects[testEmptySelectConfig]', 2);
        $I->fillField('.password_input', 'test-password');
    }

    /** @param AcceptanceAdminTester $I */
    private function checkModifiedSettingsNotEmpty(AcceptanceAdminTester $I): void
    {
        $I->seeCheckboxIsChecked('confbools[testEmptyBoolConfig]');
        $I->canSeeInField('confstrs[testEmptyStrConfig]', 'new-string');
        $I->canSeeInField('confarrs[testEmptyArrConfig]', "new-option-1\nnew-option-2");
        $I->canSeeInField('confaarrs[testEmptyAArrConfig]', "key1 => new-option-1\nkey2 => new-option-2");
        $I->canSeeInField('confselects[testEmptySelectConfig]', 2);
        $I->dontSee('confpassword[testEmptyPasswordConfig]');
        $I->canSeeInField('confpassword[testEmptyPasswordConfig]', '');
    }
}
