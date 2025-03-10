<?php

namespace BarrelStrength\Sprout\datastudio\controllers;

use BarrelStrength\Sprout\datastudio\components\elements\DataSetElement;
use BarrelStrength\Sprout\datastudio\datasets\DataSetHelper;
use BarrelStrength\Sprout\datastudio\datasources\DataSourceInterface;
use BarrelStrength\Sprout\datastudio\DataStudioModule;
use BarrelStrength\Sprout\datastudio\reports\ExportHelper;
use Craft;
use craft\base\Element;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\models\Site;
use craft\web\Controller;
use http\Exception\InvalidArgumentException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class DataSetController extends Controller
{
    public function actionDataSetIndexTemplate(): Response
    {
        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $this->requirePermission(DataStudioModule::p('accessModule'));

        return $this->renderTemplate('sprout-module-data-studio/_datasets/index.twig', [
            'title' => DataSetElement::pluralDisplayName(),
            'elementType' => DataSetElement::class,
            'newDataSetButtonHtml' => DataSetHelper::getNewDataSetButtonHtml($site),
        ]);
    }

    public function actionResultsIndexTemplate(DataSetElement $dataSet = null, int $dataSetId = null): Response
    {
        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        if ($dataSet === null && $dataSetId) {
            $dataSet = Craft::$app->getElements()->getElementById($dataSetId, DataSetElement::class, $site->id);
        }

        if (!$dataSet) {
            throw new NotFoundHttpException('Data set not found.');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        $dataSource = $dataSet->getDataSource();

        if (!$currentUser->can(DataStudioModule::p('viewReports:' . $dataSource::class))) {
            throw new ForbiddenHttpException('User is not authorized to perform this action.');
        }

        [$labels, $values] = DataSetHelper::getLabelsAndValues($dataSet, $dataSource);

        //$visualizationSettings = $dataSet->getSetting('visualization');
        //
        //$visualizationType = $visualizationSettings['type'] ?? null;
        //$visualization = class_exists($visualizationType) ? new $visualizationType() : null;
        //
        //if ($visualization instanceof Visualization) {
        //    $visualization->setSettings($visualizationSettings);
        //    $visualization->setLabels($labels);
        //    $visualization->setValues($values);
        //} else {
        //    $visualization = null;
        //}

        if ($visualization = $dataSet->getVisualization()) {
            // @todo - review the setLabels/setValues stuff.
            // May duplicate efforts on dataSet and visualization...
            $visualization->setLabels($labels);
            $visualization->setValues($values);
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        $label = Craft::t('sprout-module-data-studio', 'Export');

        $disabledExportButtonHtml = Html::submitButton($label, [
            'class' => ['btn', 'disabled'],
            'id' => 'btn-download-csv',
            'href' => DataStudioModule::getUpgradeUrl(),
            'title' => DataStudioModule::getUpgradeMessage(),
            'style' => 'cursor: not-allowed;',
            'disabled' => 'disabled',
        ]);

        return $this->renderTemplate('sprout-module-data-studio/_datasets/results.twig', [
            'dataSet' => $dataSet,
            'visualization' => $visualization,
            'dataSource' => $dataSource,
            'labels' => $labels,
            'values' => $values,
            'canEditDataSet' => $currentUser->can(DataStudioModule::p('editDataSet:' . $dataSource::class)),
            'disabledExportButtonHtml' => Template::raw($disabledExportButtonHtml),
        ]);
    }

    public function actionCreateDataSet(string $type = null): Response
    {
        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $type = Craft::$app->getRequest()->getParam('type', $type);

        if (!$type) {
            throw new InvalidArgumentException('No Data Set Type provided.');
        }

        $dataSourceType = new $type();

        if (!$dataSourceType instanceof DataSourceInterface) {
            throw new MissingComponentException('Unable to create data source of type: ' . $type);
        }

        $dataSet = Craft::createObject(DataSetElement::class);
        $dataSet->siteId = $site->id;
        $dataSet->type = $dataSourceType::class;

        $user = Craft::$app->getUser()->getIdentity();

        if (!$dataSet->canSave($user)) {
            throw new ForbiddenHttpException('User not authorized to save this data set.');
        }

        $dataSet->setScenario(Element::SCENARIO_ESSENTIALS);

        if (!Craft::$app->getDrafts()->saveElementAsDraft($dataSet, Craft::$app->getUser()->getId(), null, null, false)) {
            throw new ServerErrorHttpException(sprintf('Unable to save data set as a draft: %s', implode(', ', $dataSet->getErrorSummary(true))));
        }

        // Supports creating a new Data Set via Slideout Editor
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'elementId' => $dataSet->id,
                'draftId' => $dataSet->draftId,
                'siteId' => $dataSet->siteId,
            ]);
        }

        return $this->redirect($dataSet->getCpEditUrl());
    }

    public function actionExportDataSet(): void
    {
        if (!DataStudioModule::isPro()) {
            throw new ForbiddenHttpException('Upgrade to Sprout Data Studio Pro to export data sets.');
        }

        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $dataSetId = Craft::$app->getRequest()->getParam('dataSetId');

        $dataSet = Craft::$app->getElements()->getElementById($dataSetId, DataSetElement::class, $site->id);

        if (!$dataSet instanceof DataSetElement) {
            throw new ElementNotFoundException('Data set not found');
        }

        $dataSource = $dataSet->getDataSource();

        if (!$currentUser->can(DataStudioModule::p('viewReports:' . $dataSource::class))) {
            throw new ForbiddenHttpException('User not authorized to view this data set.');
        }

        $filename = $dataSet . '-' . date('Ymd-his');

        $dataSource->isExport = true;
        $labels = $dataSource->getDefaultLabels($dataSet);
        $values = $dataSource->getResults($dataSet);

        ExportHelper::toCsv($values, $labels, $filename, $dataSet->delimiter);
    }

    public function actionUpdateDataSet(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $dataSetId = $request->getBodyParam('dataSetId');
        $settings = $request->getBodyParam('settings');

        $dataSet = new DataSetElement();

        if ($dataSetId && $settings) {
            $dataSet = Craft::$app->getElements()->getElementById($dataSetId, DataSetElement::class);

            if (!$dataSet instanceof DataSetElement) {
                throw new NotFoundHttpException('No data set exists with the ID: ' . $dataSetId);
            }

            $currentUser = Craft::$app->getUser()->getIdentity();
            $dataSource = $dataSet->getDataSource();

            if (!$currentUser->can(DataStudioModule::p('editDataSet:' . $dataSource::class))) {
                throw new NotFoundHttpException('User does not have permission to access Data Set: ' . $dataSetId);
            }

            $dataSet->settings = $settings;
        }

        if (!Craft::$app->getElements()->saveElement($dataSet)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-base-reports', 'Could not update report.'));

            // Send the report back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'dataSet' => $dataSet,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-module-data-studio', 'Data set updated.'));

        return $this->redirectToPostedUrl($dataSet);
    }

    /**
     * Because Garnish isn't documented, still.
     */
    public function actionGetNewDataSetsButtonHtml(): Response
    {
        $this->requireAcceptsJson();

        $site = Cp::requestedSite();

        if (!$site instanceof Site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        return $this->asJson([
            'html' => DataSetHelper::getNewDataSetButtonHtml($site),
        ]);
    }
}
