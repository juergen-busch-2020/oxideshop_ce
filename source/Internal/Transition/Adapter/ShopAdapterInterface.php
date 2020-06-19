<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Transition\Adapter;

interface ShopAdapterInterface
{
    /**
     * @param string $string
     *
     * @return string
     */
    public function translateString($string): string;

    /**
     * @param string $moduleId
     */
    public function invalidateModuleCache(string $moduleId);

    /**
     * @return string
     */
    public function generateUniqueId(): string;

    /**
     * @return array
     */
    public function getShopControllerClassMap(): array;

    /**
     * @param string $namespace
     * @return bool
     */
    public function isNamespace(string $namespace): bool;

    /**
     * @param string $namespace
     * @return bool
     */
    public function isShopUnifiedNamespace(string $namespace): bool;

    /**
     * @param string $namespace
     * @return bool
     */
    public function isShopEditionNamespace(string $namespace): bool;

    /**
     * @deprecated since v6.5.5 (2020-06-17)
     *
     * @return \Smarty
     */
    public function getSmartyInstance(): \Smarty;

    /**
     * @param int $shopId
     * @return bool
     */
    public function validateShopId(int $shopId): bool;
}
