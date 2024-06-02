<?php

namespace BarrelStrength\Sprout\mailer\controllers;

use BarrelStrength\Sprout\mailer\components\elements\email\EmailElement;
use BarrelStrength\Sprout\mailer\MailerModule;
use BarrelStrength\Sprout\mailer\mailers\Mailer;
use BarrelStrength\Sprout\mailer\mailers\MailerHelper;
use BarrelStrength\Sprout\mailer\mailers\MailerSendTestInterface;
use Craft;
use craft\errors\ElementNotFoundException;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\Controller;
use Exception;
use yii\web\Response;

class MailerController extends Controller
{
    public function actionMailersIndexTemplate(): Response
    {
        $mailers = MailerHelper::getMailers();
        $mailerTypes = MailerModule::getInstance()->mailers->getMailerTypes();

        return $this->renderTemplate('sprout-module-mailer/_settings/mailers/index.twig', [
            'mailers' => $mailers,
            'mailerTypes' => $mailerTypes,
        ]);
    }

    public function actionEdit(Mailer $mailer = null, string $mailerUid = null, string $type = null): Response
    {
        $this->requireAdmin();

        if (!$mailer && $mailerUid) {
            $mailer = MailerHelper::getMailerByUid($mailerUid);
        }

        if (!$mailer && $type) {
            $mailer = new $type();
        }

        return $this->renderTemplate('sprout-module-mailer/_settings/mailers/edit.twig', [
            'mailer' => $mailer,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $mailer = $this->populateMailerModel();

        $mailers = MailerHelper::getMailers();
        $mailers[$mailer->uid] = $mailer;

        if (!$mailer->validate() || !MailerHelper::saveMailers($mailers)) {
            Craft::$app->session->setError(Craft::t('sprout-module-mailer', 'Could not save mailer.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'mailer' => $mailer,
            ]);

            return null;
        }

        Craft::$app->session->setNotice(Craft::t('sprout-module-mailer', 'Mailer saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionReorder(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin(false);

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));

        if (!MailerHelper::reorderMailers($ids)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('sprout-module-mailer', "Couldn't reorder mailers."),
            ]);
        }

        return $this->asJson([
            'success' => true,
        ]);
    }

    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin(false);

        $mailerUid = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $mailers = MailerHelper::getMailers();

        $inUse = false;
        foreach ($mailers as $mailer) {
            if ($mailer->uid === $mailerUid) {
                $inUse = true;
                break;
            }
        }

        if ($inUse || !MailerHelper::removeMailer($mailerUid)) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }

    public function actionGetSendTestHtml(): Response
    {
        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');

        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        if (!$email) {
            throw new ElementNotFoundException('Email not found.');
        }

        $mailer = $email->getMailer();

        if (!$mailer instanceof MailerSendTestInterface) {
            throw new ElementNotFoundException('Incorrect mailer type.');
        }

        return $this->asJson([
            'success' => true,
            'html' => $mailer->getSendTestModalHtml($email),
        ]);
    }

    public function actionSendTest(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $emailId = $request->getRequiredBodyParam('emailId');
        $mailerInstructionsSettings = $request->getRequiredBodyParam('mailerInstructionsSettings');

        /** @var EmailElement $email */
        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        $mailerInstructionsTestSettings = $email->getMailerInstructions($mailerInstructionsSettings);

        if (!$mailerInstructionsTestSettings->validate()) {
            return $this->asJson([
                'success' => false,
                'errors' => $mailerInstructionsTestSettings->getErrors(),
            ]);
        }

        $mailer = $email->getMailer();

        try {
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
        $mailerInstructionsSettings = $request->getRequiredBodyParam('mailerInstructionsSettings');

        /** @var EmailElement $email */
        $email = Craft::$app->getElements()->getElementById($emailId, EmailElement::class);

        if ($mailerInstructionsSettings) {
            $email->mailerInstructionsSettings = $mailerInstructionsSettings;
        }

        $mailerInstructionsSettings = $email->getMailerInstructions();

        if (!$mailerInstructionsSettings->validate()) {
            return $this->asJson([
                'success' => false,
                'errors' => $mailerInstructionsSettings->getErrors(),
            ]);
        }

        $mailer = $email->getMailer();

        try {
            $mailer->send($email, $mailerInstructionsSettings);
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
        $type = Craft::$app->getRequest()->getRequiredBodyParam('type');
        $uid = Craft::$app->getRequest()->getRequiredBodyParam('uid');

        /** @var Mailer $mailer */
        $mailer = new $type();
        $mailer->name = Craft::$app->getRequest()->getRequiredBodyParam('name');
        $mailer->uid = !empty($uid) ? $uid : StringHelper::UUID();

        $settings = Craft::$app->getRequest()->getBodyParam('settings');
        $mailer->setAttributes($settings, false);

        return $mailer;
    }
}
