<?php

namespace BarrelStrength\Sprout\meta\controllers;

use BarrelStrength\Sprout\fields\FieldsModule;
use BarrelStrength\Sprout\meta\globals\Globals;
use BarrelStrength\Sprout\meta\MetaModule;
use Craft;
use craft\elements\Address;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;
use http\Exception\RuntimeException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class GlobalMetadataController extends Controller
{
    public function actionHello(): Response
    {
        return $this->redirect(UrlHelper::cpUrl('sprout/meta/globals/website-identity'));
    }

    /**
     * Renders Global Metadata edit pages
     */
    public function actionEditGlobalMetadata(string $selectedTabHandle, Globals $globals = null): Response
    {
        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        // Get the sites the user is allowed to edit
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();

        if (empty($editableSiteIds)) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites');
        }

        // Make sure the user has permission to edit that site
        if (!in_array($site->id, $editableSiteIds, false)) {
            throw new ForbiddenHttpException('User not permitted to edit content in this site');
        }

        if ($globals === null) {
            $globals = MetaModule::getInstance()->globalMetadata->getGlobalMetadata($site);
            $globals->siteId = $site->id;
        }

        if (!$globals->addressModel) {
            $address = $this->createAddress($globals, $site);
            $globals->addressModel = $address;
        }

        $locationField = Cp::elementCardHtml($globals->addressModel, [
            'context' => 'field',
            'inputName' => 'locationAddressId',
            'showActionMenu' => true,
        ]);

        $sites = Craft::$app->getSites()->getEditableSites();

        $crumbs[] = [
            'icon' => Cp::earthIcon(),
            'label' => $site->name,
            'menu' => [
                'label' => Craft::t('sprout-module-sitemaps', 'Select site'),
                'items' => Cp::siteMenuItems($sites, $site),
            ],
        ];

        return $this->renderTemplate('sprout-module-meta/globals/' . $selectedTabHandle, [
            'globals' => $globals,
            'settings' => MetaModule::getInstance()->getSettings(),
            'currentSite' => $site,
            'selectedTabHandle' => $selectedTabHandle,
            'locationField' => $locationField,
            'countryOptions' => FieldsModule::getInstance()->phoneHelper::getCountries(),
            'crumbs' => $crumbs,
        ]);
    }

    public function actionSaveGlobalMetadata(): ?Response
    {
        $this->requirePostRequest();

        $postData = Craft::$app->getRequest()->getBodyParam('meta.globals');
        $globalColumn = Craft::$app->getRequest()->getBodyParam('globalColumn');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        // Adjust Founding Date post data
        if (isset($postData['identity']['foundingDate'])) {
            $postData['identity']['foundingDate'] = DateTimeHelper::toDateTime($postData['identity']['foundingDate']);
        }

        // Adjust Schema Organization post data
        if (isset($postData['identity']['@type']) && $postData['identity']['@type'] === 'Person') {
            // Clean up our organization subtypes when the Person type is selected
            unset($postData['identity']['organizationSubTypes']);
        }

        $globals = new Globals($postData);
        $globals->siteId = $siteId;

        if (!MetaModule::getInstance()->globalMetadata->saveGlobalMetadata($globalColumn, $globals)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-module-meta', 'Unable to save globals.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'globals' => $globals,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-module-meta', 'Globals saved.'));

        return $this->redirectToPostedUrl($globals);
    }

    /**
     * Save the Verify Ownership Structured Data to the database
     */
    public function actionSaveVerifyOwnership(): ?Response
    {
        $config = [];
        $this->requirePostRequest();

        $ownershipMeta = Craft::$app->getRequest()->getBodyParam('meta.meta.ownership');
        $globalColumn = 'ownership';
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        $ownershipMetaWithKeys = null;

        // Remove empty items from multi-dimensional array
        if ($ownershipMeta) {
            $ownershipMeta = array_filter(array_map('array_filter', $ownershipMeta));

            foreach ($ownershipMeta as $key => $meta) {
                if (count($meta) === 3) {
                    $ownershipMetaWithKeys[$key]['service'] = $meta[0];
                    $ownershipMetaWithKeys[$key]['metaTag'] = $meta[1];
                    $ownershipMetaWithKeys[$key]['verificationCode'] = $meta[2];
                }
            }
        }

        $config[$globalColumn] = $ownershipMetaWithKeys;

        $globals = new Globals($config);
        $globals->siteId = $siteId;

        if (!MetaModule::getInstance()->globalMetadata->saveGlobalMetadata($globalColumn, $globals)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-module-meta', 'Unable to save globals.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'globals' => $globals,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-module-meta', 'Globals saved.'));

        return $this->redirectToPostedUrl($globals);
    }

    public function createAddress(Globals $globals, Site $site): Address
    {
        $address = new Address();
        $address->title = Craft::t('sprout-module-meta', 'Website Identity');
        Craft::$app->getElements()->saveElement($address);

        $updatedGlobals = new Globals([
            'siteId' => $site->id,
            'identity' => array_merge($globals->getIdentity(), [
                'locationAddressId' => $address->id,
            ]),
        ]);

        if (!MetaModule::getInstance()->globalMetadata->saveGlobalMetadata('identity', $updatedGlobals)) {
            throw new RuntimeException('Error configuring global address.');
        }

        return $address;
    }
}
