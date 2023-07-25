<?php

namespace BarrelStrength\Sprout\mailer\controllers;

use BarrelStrength\Sprout\mailer\components\elements\email\EmailElement;
use BarrelStrength\Sprout\mailer\MailerModule;
use BarrelStrength\Sprout\mailer\mailers\Mailer;
use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;
use Exception;
use yii\web\Response;

class MailerController extends Controller
{
    public function actionMailersIndexTemplate(): Response
    {
        $mailers = MailerModule::getInstance()->mailers->getMailers();

        return $this->renderTemplate('sprout-module-mailer/_settings/mailers/index.twig', [
            'mailers' => $mailers,
        ]);
    }

    public function actionEdit(Mailer $mailer = null, string $mailerUid = null): Response
    {
        $this->requireAdmin();

        if (!$mailer) {
            $mailer = MailerModule::getInstance()->mailers->getMailerByUid($mailerUid);
        }

        return $this->renderTemplate('sprout-module-mailer/_settings/mailers/edit.twig', [
            'mailer' => $mailer,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $mailerModel = $this->populateMailerModel();

        $settingsKey = $mailerModel->uid;
        $configPath = MailerModule::projectConfigPath('mailers.' . $settingsKey);

        if (!$mailerModel->validate() || !Craft::$app->getProjectConfig()->set($configPath, $mailerModel->getConfig(), "Update Sprout Settings for “{$configPath}”")) {
            Craft::$app->session->setError(Craft::t('sprout-module-mailer', 'Could not save Email Type.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'emailTheme' => $mailerModel,
            ]);

            return null;
        }

        Craft::$app->session->setNotice(Craft::t('sprout-module-mailer', 'Email Type saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionGetSendTestHtml(): Response
    {
        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');

        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        return $this->asJson([
            'success' => true,
            'html' => $email->getMailer()->getSendTestModalHtml($email),
        ]);
    }

    public function actionSendTest(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $emailId = $request->getRequiredBodyParam('emailId');
        $settings = $request->getRequiredBodyParam('mailerInstructionsSettings');

        /** @var EmailElement $email */
        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        $mailer = $email->getMailer();
        $mailerInstructionsTestSettings = $mailer->createMailerInstructionsTestSettingsModel();
        $mailerInstructionsTestSettings->setAttributes($settings, false);

        if (!$mailerInstructionsTestSettings->validate()) {
            return $this->asJson([
                'success' => false,
                'errors' => $mailerInstructionsTestSettings->getErrors(),
            ]);
        }

        try {
            $mailer = $email->getMailer();
            $mailer->send($email, $mailerInstructionsTestSettings);
        } catch (Exception) {
            return $this->asJson([
                'success' => false,
                'errors' => $email->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
        ]);
    }

    public function actionSend(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $emailId = $request->getRequiredBodyParam('emailId');
        $settings = $request->getRequiredBodyParam('mailerInstructionsSettings');

        /** @var EmailElement $email */
        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        $mailer = $email->getMailer();
        $mailerInstructionsSettings = $mailer->createMailerInstructionsSettingsModel();
        $mailerInstructionsSettings->setAttributes($settings, false);

        try {
            $email->send($mailerInstructionsSettings);
        } catch (Exception) {
            return $this->asJson([
                'success' => false,
                'errors' => $email->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
        ]);
    }

    private function populateMailerModel(): Mailer
    {
        $mailerUid = Craft::$app->request->getBodyParam('mailerUid');

        $mailer = MailerModule::getInstance()->mailers->getMailerByUid($mailerUid);

        $mailer->id = $mailerUid;
        $mailer->name = Craft::$app->request->getBodyParam('name');
        $mailer->mailerSettings = Craft::$app->request->getBodyParam('mailerSettings');
        $mailer->setAttributes($mailer->mailerSettings, false);

        $isNew = !$mailer->id;

        if ($isNew) {
            $mailer->uid = StringHelper::UUID();
        } else {
            $settings = MailerModule::getInstance()->getSettings();
            $mailer->uid = $settings->systemMailer->uid;
        }

        return $mailer;
    }
}
