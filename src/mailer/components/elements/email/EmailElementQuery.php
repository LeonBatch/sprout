<?php

namespace BarrelStrength\Sprout\mailer\components\elements\email;

use BarrelStrength\Sprout\mailer\emailtypes\EmailTypeHelper;
use BarrelStrength\Sprout\mailer\emailvariants\EmailVariant;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class EmailElementQuery extends ElementQuery
{
    public ?string $subjectLine = null;

    public string|array|null $emailVariantType = null;

    public ?string $emailTypeUid = null;

    public function subjectLine(string $value): static
    {
        $this->subjectLine = $value;

        return $this;
    }

    public function emailVariantType(string|array $value): static
    {
        $this->emailVariantType = $value;

        return $this;
    }

    public function emailTypeUid(string $value): static
    {
        $this->emailTypeUid = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('sprout_emails');

        $this->query->select([
            'sprout_emails.subjectLine',
            'sprout_emails.preheaderText',
            'sprout_emails.defaultMessage',
            'sprout_emails.emailVariantType',
            'sprout_emails.emailVariantSettings',
            'sprout_emails.mailerInstructionsSettings',
            'sprout_emails.emailTypeUid',
            'sprout_emails.dateCreated',
            'sprout_emails.dateUpdated',
        ]);

        if ($this->subjectLine) {
            $this->subQuery->andWhere(Db::parseParam('sprout_emails.subjectLine', $this->subjectLine));
        }

        if ($this->emailVariantType) {
            $this->subQuery->andWhere(Db::parseParam('sprout_emails.emailVariantType', $this->emailVariantType));
        }

        if ($this->emailTypeUid) {
            $this->subQuery->andWhere(Db::parseParam('sprout_emails.emailTypeUid', $this->emailTypeUid));
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        if (!$this->emailVariantType) {
            return parent::statusCondition($status);
        }

        /** @var EmailVariant $emailVariant */
        $emailVariant = new $this->emailVariantType();

        if (!$emailVariant->hasCustomStatuses()) {
            return parent::statusCondition($status);
        }

        return $emailVariant->getStatusCondition($status);
    }

    protected function fieldLayouts(): array
    {
        if (!$this->emailTypeUid) {
            return parent::fieldLayouts();
        }

        $layout = EmailTypeHelper::getEmailTypeByUid($this->emailTypeUid);

        if ($layout) {
            return [$layout->getFieldLayout()];
        }

        return parent::fieldLayouts();
    }
}
