<?php
/**
 * This file is part of OXID eShop Community Edition.
 *
 * OXID eShop Community Edition is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eShop Community Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2015
 * @version   OXID eShop CE
 */

/**
 * Main shopping basket manager. Arranges shopping basket
 * contents, updates amounts, prices, taxes etc.
 *
 * @subpackage oxcmp
 */
class oxcmp_basket extends oxView
{

    /**
     * Marking object as component
     *
     * @var bool
     */
    protected $_blIsComponent = true;

    /**
     * Last call function name
     *
     * @var string
     */
    protected $_sLastCallFnc = null;

    /**
     * Parameters which are kept when redirecting after user
     * puts something to basket
     *
     * @var array
     */
    public $aRedirectParams = array('cnid', // category id
                                    'mnid', // manufacturer id
                                    'anid', // active article id
                                    'tpl', // spec. template
                                    'listtype', // list type
                                    'searchcnid', // search category
                                    'searchvendor', // search vendor
                                    'searchmanufacturer', // search manufacturer
                                    'searchtag', // search tag
                                    'searchrecomm', // search recomendation
                                    'recommid' // recomm. list id
    );

    /**
     * Initiates component.
     */
    public function init()
    {
        $oConfig = $this->getConfig();
        if ($oConfig->getConfigParam('blPsBasketReservationEnabled')) {
            if ($oReservations = $this->getSession()->getBasketReservations()) {
                if (!$oReservations->getTimeLeft()) {
                    $oBasket = $this->getSession()->getBasket();
                    if ($oBasket && $oBasket->getProductsCount()) {
                        $oBasket->deleteBasket();
                    }
                }
                $iLimit = (int) $oConfig->getConfigParam('iBasketReservationCleanPerRequest');
                if (!$iLimit) {
                    $iLimit = 200;
                }
                $oReservations->discardUnusedReservations($iLimit);
            }
        }

        parent::init();

        // Basket exclude
        if ($this->getConfig()->getConfigParam('blBasketExcludeEnabled')) {
            if ($oBasket = $this->getSession()->getBasket()) {
                $this->getParent()->setRootCatChanged($this->isRootCatChanged() && $oBasket->getContents());
            }
        }
    }

    /**
     * Loads basket ($oBasket = $mySession->getBasket()), calls oBasket->calculateBasket,
     * executes parent::render() and returns basket object.
     *
     * @return object   $oBasket    basket object
     */
    public function render()
    {
        // recalculating
        if ($oBasket = $this->getSession()->getBasket()) {
            $oBasket->calculateBasket(false);
        }

        parent::render();

        return $oBasket;
    }

    /**
     * Basket content update controller.
     * Before adding article - check if client is not a search engine. If
     * yes - exits method by returning false. If no - executes
     * oxcmp_basket::_addItems() and puts article to basket.
     * Returns position where to redirect user browser.
     *
     * @param string $sProductId Product ID (default null)
     * @param double $dAmount    Product amount (default null)
     * @param array  $aSel       (default null)
     * @param array  $aPersParam (default null)
     * @param bool   $blOverride If true means increase amount of chosen article (default false)
     *
     * @return mixed
     */
    public function tobasket($sProductId = null, $dAmount = null, $aSel = null, $aPersParam = null, $blOverride = false)
    {
        // adding to basket is not allowed ?
        $myConfig = $this->getConfig();
        if (oxRegistry::getUtils()->isSearchEngine()) {
            return;
        }

        // adding articles
        if ($aProducts = $this->_getItems($sProductId, $dAmount, $aSel, $aPersParam, $blOverride)) {

            $this->_setLastCallFnc('tobasket');
            $oBasketItem = $this->_addItems($aProducts);

            // new basket item marker
            if ($oBasketItem && $myConfig->getConfigParam('iNewBasketItemMessage') != 0) {
                $oNewItem = new stdClass();
                $oNewItem->sTitle = $oBasketItem->getTitle();
                $oNewItem->sId = $oBasketItem->getProductId();
                $oNewItem->dAmount = $oBasketItem->getAmount();
                $oNewItem->dBundledAmount = $oBasketItem->getdBundledAmount();

                // passing article
                oxRegistry::getSession()->setVariable('_newitem', $oNewItem);
            }


            // redirect to basket
            return $this->_getRedirectUrl();
        }
    }

    /**
     * Similar to tobasket, except that as product id "bindex" parameter is (can be) taken
     *
     * @param string $sProductId Product ID (default null)
     * @param double $dAmount    Product amount (default null)
     * @param array  $aSel       (default null)
     * @param array  $aPersParam (default null)
     * @param bool   $blOverride If true means increase amount of chosen article (default false)
     *
     * @return mixed
     */
    public function changebasket(
        $sProductId = null,
        $dAmount = null,
        $aSel = null,
        $aPersParam = null,
        $blOverride = true
    ) {
        // adding to basket is not allowed ?
        if (oxRegistry::getUtils()->isSearchEngine()) {
            return;
        }

        // fetching item ID
        if (!$sProductId) {
            $sBasketItemId = oxRegistry::getConfig()->getRequestParameter('bindex');

            if ($sBasketItemId) {
                $oBasket = $this->getSession()->getBasket();
                //take params
                $aBasketContents = $oBasket->getContents();
                $oItem = $aBasketContents[$sBasketItemId];

                $sProductId = isset($oItem) ? $oItem->getProductId() : null;
            } else {
                $sProductId = oxRegistry::getConfig()->getRequestParameter('aid');
            }
        }

        // fetching other needed info
        $dAmount = isset($dAmount) ? $dAmount : oxRegistry::getConfig()->getRequestParameter('am');
        $aSel = isset($aSel) ? $aSel : oxRegistry::getConfig()->getRequestParameter('sel');
        $aPersParam = $aPersParam ? $aPersParam : oxRegistry::getConfig()->getRequestParameter('persparam');

        // adding articles
        if ($aProducts = $this->_getItems($sProductId, $dAmount, $aSel, $aPersParam, $blOverride)) {

            // information that last call was changebasket
            $oBasket = $this->getSession()->getBasket();
            $oBasket->onUpdate();

            $this->_setLastCallFnc('changebasket');
            $oBasketItem = $this->_addItems($aProducts);
        }

    }

    /**
     * Formats and returns redirect URL where shop must be redirected after
     * storing something to basket
     *
     * @return string   $sClass.$sPosition  redirection URL
     */
    protected function _getRedirectUrl()
    {

        // active class
        $sClass = oxRegistry::getConfig()->getRequestParameter('cl');
        $sClass = $sClass ? $sClass . '?' : 'start?';
        $sPosition = '';

        // setting redirect parameters
        foreach ($this->aRedirectParams as $sParamName) {
            $sParamVal = oxRegistry::getConfig()->getRequestParameter($sParamName);
            $sPosition .= $sParamVal ? $sParamName . '=' . $sParamVal . '&' : '';
        }

        // special treatment
        // search param
        $sParam = rawurlencode(oxRegistry::getConfig()->getRequestParameter('searchparam', true));
        $sPosition .= $sParam ? 'searchparam=' . $sParam . '&' : '';

        // current page number
        $iPageNr = (int) oxRegistry::getConfig()->getRequestParameter('pgNr');
        $sPosition .= ($iPageNr > 0) ? 'pgNr=' . $iPageNr . '&' : '';

        // reload and backbutton blocker
        if ($this->getConfig()->getConfigParam('iNewBasketItemMessage') == 3) {

            // saving return to shop link to session
            oxRegistry::getSession()->setVariable('_backtoshop', $sClass . $sPosition);

            // redirecting to basket
            $sClass = 'basket?';
        }

        return $sClass . $sPosition;
    }

    /**
     * Cleans and returns persisted parameters.
     *
     * @param array $persistedParameters key-value parameters (optional). If not passed - takes parameters from request.
     *
     * @return array|null cleaned up parameters or null, if there are no non-empty parameters
     */
    protected function getPersistedParameters($persistedParameters = null)
    {
        $persistedParameters = ($persistedParameters ?: oxRegistry::getConfig()->getRequestParameter('persparam'));
        if (!is_array($persistedParameters)) {
            return null;
        }
        return array_filter($persistedParameters, 'trim') ?: null;
    }

    /**
     * Collects and returns array of items to add to basket. Product info is taken not only from
     * given parameters, but additionally from request 'aproducts' parameter
     *
     * @param string $sProductId product ID
     * @param double $dAmount    product amount
     * @param array  $aSel       product select lists
     * @param array  $aPersParam product persistent parameters
     * @param bool   $blOverride amount override status
     *
     * @return mixed
     */
    protected function _getItems(
        $sProductId = null,
        $dAmount = null,
        $aSel = null,
        $aPersParam = null,
        $blOverride = false
    ) {
        // collecting items to add
        $aProducts = oxRegistry::getConfig()->getRequestParameter('aproducts');

        // collecting specified item
        $sProductId = $sProductId ? $sProductId : oxRegistry::getConfig()->getRequestParameter('aid');
        if ($sProductId) {

            // additionally fetching current product info
            $dAmount = isset($dAmount) ? $dAmount : oxRegistry::getConfig()->getRequestParameter('am');

            // select lists
            $aSel = isset($aSel) ? $aSel : oxRegistry::getConfig()->getRequestParameter('sel');

            // persistent parameters
            if (empty($aPersParam)) {
                $aPersParam = $this->getPersistedParameters();
            }

            $sBasketItemId = oxRegistry::getConfig()->getRequestParameter('bindex');

            $aProducts[$sProductId] = array('am'           => $dAmount,
                                            'sel'          => $aSel,
                                            'persparam'    => $aPersParam,
                                            'override'     => $blOverride,
                                            'basketitemid' => $sBasketItemId
            );
        }

        if (is_array($aProducts) && count($aProducts)) {

            if (oxRegistry::getConfig()->getRequestParameter('removeBtn') !== null) {
                //setting amount to 0 if removing article from basket
                foreach ($aProducts as $sProductId => $aProduct) {
                    if (isset($aProduct['remove']) && $aProduct['remove']) {
                        $aProducts[$sProductId]['am'] = 0;
                    } else {
                        unset ($aProducts[$sProductId]);
                    }
                }
            }

            return $aProducts;
        }

        return false;
    }

    /**
     * Adds all articles user wants to add to basket. Returns
     * last added to basket item.
     *
     * @param array $products products to add array
     *
     * @return  object  $oBasketItem    last added basket item
     */
    protected function _addItems($products)
    {
        $activeView = $this->getConfig()->getActiveView();
        $errorDestination = $activeView->getErrorDestination();

        $basket = $this->getSession()->getBasket();
        $basketInfo = $basket->getBasketSummary();

        $basketItemAmounts = array();

        foreach ($products as $addProductId => $productInfo) {

            $sProductId = isset($productInfo['aid']) ? $productInfo['aid'] : $addProductId;

            // collecting input
            $productAmount = $basketInfo->aArticles[$sProductId];
            $products[$addProductId]['oldam'] = isset($productAmount) ? $productAmount : 0;

            $amount = isset($productInfo['am']) ? $productInfo['am'] : 0;
            $aSelList = isset($productInfo['sel']) ? $productInfo['sel'] : null;
            $aParams = $productInfo['persparam'];
            $aPersParam = $this->getPersistedParameters($productInfo['persparam']);
            $blOverride = isset($productInfo['override']) ? $productInfo['override'] : null;
            $blIsBundle = isset($productInfo['bundle']) ? true : false;
            $sOldBasketItemId = isset($productInfo['basketitemid']) ? $productInfo['basketitemid'] : null;

            try {

                //0005928 fix, if we already changed articles so they now exactly match existing ones,
                //we need to make sure we get the amounts correct
                if (isset($basketItemAmounts[$sOldBasketItemId])) {
                    $amount = $amount + $basketItemAmounts[$sOldBasketItemId];
                }

                $basketItem = $basket->addToBasket(
                    $sProductId,
                    $amount,
                    $aSelList,
                    $aPersParam,
                    $blOverride,
                    $blIsBundle,
                    $sOldBasketItemId
                );

                if (is_a($basketItem, 'oxBasketItem')) {
                    $basketItemId = $basketItem->getBasketItemKey();
                }
                if (!empty($basketItemId)) {
                    $basketItemAmounts[$basketItemId] += $amount;
                }

            } catch (oxOutOfStockException $exception) {
                $exception->setDestination($errorDestination);
                // #950 Change error destination to basket popup
                if (!$errorDestination && $this->getConfig()->getConfigParam('iNewBasketItemMessage') == 2) {
                    $errorDestination = 'popup';
                }
                oxRegistry::get("oxUtilsView")->addErrorToDisplay($exception, false, (bool) $errorDestination, $errorDestination);
            } catch (oxArticleInputException $exception) {
                //add to display at specific position
                $exception->setDestination($errorDestination);
                oxRegistry::get("oxUtilsView")->addErrorToDisplay($exception, false, (bool) $errorDestination, $errorDestination);
            } catch (oxNoArticleException $exception) {
                //ignored, best solution F ?
            }
            if (!$basketItem) {
                $info = $basket->getBasketSummary();
                $productAmount = $info->aArticles[$sProductId];
                $products[$addProductId]['am'] = isset($productAmount) ? $productAmount : 0;
            }
        }

        //if basket empty remove possible gift card
        if ($basket->getProductsCount() == 0) {
            $basket->setCardId(null);
        }

        // information that last call was tobasket
        $this->_setLastCall($this->_getLastCallFnc(), $products, $basketInfo);

        return $basketItem;
    }

    /**
     * Setting last call data to session (data used by econda)
     *
     * @param string $sCallName    name of action ('tobasket', 'changebasket')
     * @param array  $aProductInfo data which comes from request when you press button "to basket"
     * @param array  $aBasketInfo  array returned by oxbasket::getBasketSummary()
     */
    protected function _setLastCall($sCallName, $aProductInfo, $aBasketInfo)
    {
        oxRegistry::getSession()->setVariable('aLastcall', array($sCallName => $aProductInfo));
    }

    /**
     * Setting last call function name (data used by econda)
     *
     * @param string $sCallName name of action ('tobasket', 'changebasket')
     */
    protected function _setLastCallFnc($sCallName)
    {
        $this->_sLastCallFnc = $sCallName;
    }

    /**
     * Getting last call function name (data used by econda)
     *
     * @return string
     */
    protected function _getLastCallFnc()
    {
        return $this->_sLastCallFnc;
    }

    /**
     * Returns true if active root category was changed
     *
     * @return bool
     */
    public function isRootCatChanged()
    {
        // in Basket
        $oBasket = $this->getSession()->getBasket();
        if ($oBasket->showCatChangeWarning()) {
            $oBasket->setCatChangeWarningState(false);

            return true;
        }

        // in Category, only then category is empty ant not equal to default category
        $sDefCat = oxRegistry::getConfig()->getActiveShop()->oxshops__oxdefcat->value;
        $sActCat = oxRegistry::getConfig()->getRequestParameter('cnid');
        $oActCat = oxnew('oxcategory');
        if ($sActCat && $sActCat != $sDefCat && $oActCat->load($sActCat)) {
            $sActRoot = $oActCat->oxcategories__oxrootid->value;
            if ($oBasket->getBasketRootCatId() && $sActRoot != $oBasket->getBasketRootCatId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executes user choice:
     *
     * - if user clicked on "Proceed to checkout" - redirects to basket,
     * - if clicked "Continue shopping" - clear basket
     *
     * @return mixed
     */
    public function executeuserchoice()
    {

        // redirect to basket
        if (oxRegistry::getConfig()->getRequestParameter("tobasket")) {
            return "basket";
        } else {
            // clear basket
            $this->getSession()->getBasket()->deleteBasket();
            $this->getParent()->setRootCatChanged(false);
        }
    }

}
