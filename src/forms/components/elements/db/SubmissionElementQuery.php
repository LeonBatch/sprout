<?php

namespace BarrelStrength\Sprout\forms\components\elements\db;

use BarrelStrength\Sprout\forms\db\SproutTable;
use BarrelStrength\Sprout\forms\FormsModule;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class SubmissionElementQuery extends ElementQuery
{
    public ?int $statusId = null;

    public int|array|null $formId = null;

    public string $formHandle = '';

    public string $formName = '';

    public array|string|null $status = [];

    private bool $excludeSpam = true;

    public function __construct(string $elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'sprout_form_submissions.id';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * Sets the [[statusId]] property.
     *
     * @return static self reference
     */
    public function statusId(int $value): SubmissionElementQuery
    {
        $this->statusId = $value;

        return $this;
    }

    /**
     * Sets the [[formId]] property.
     *
     * @return static self reference
     */
    public function formId(int|array|null $value): SubmissionElementQuery
    {
        $this->formId = $value;

        return $this;
    }

    /**
     * Sets the [[formHandle]] property.
     *
     * @return static self reference
     */
    public function formHandle(string $value): SubmissionElementQuery
    {
        $this->formHandle = $value;
        $form = FormsModule::getInstance()->forms->getFormByHandle($value);
        // To add support to filtering we need to have the formId set.
        if ($form !== null) {
            $this->formId = $form->id;
        }

        return $this;
    }

    /**
     * Sets the [[formName]] property.
     *
     * @return static self reference
     */
    public function formName(string $value): SubmissionElementQuery
    {
        $this->formName = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('sprout_form_submissions');

        $this->query->select([
            'sprout_form_submissions.formId',
            'sprout_form_submissions.statusId',
            'sprout_form_submissions.title',
            'sprout_form_submissions.formMetadata',
            'sprout_form_submissions.dateCreated',
            'sprout_form_submissions.dateUpdated',
            'sprout_form_submissions.uid',
            'sprout_forms.name as formName',
            'sprout_forms.handle as formHandle',
            'sprout_form_submissions_statuses.handle as statusHandle',
        ]);

        $this->query->innerJoin(['sprout_forms' => SproutTable::FORMS], '[[sprout_forms.id]] = [[sprout_form_submissions.formId]]');
        $this->query->innerJoin(['sprout_form_submissions_statuses' => SproutTable::FORM_SUBMISSIONS_STATUSES], '[[sprout_form_submissions_statuses.id]] = [[sprout_form_submissions.statusId]]');

        if ($this->formId) {
            $this->subQuery->andWhere(Db::parseParam(
                'sprout_form_submissions.formId', $this->formId
            ));
        }

        if ($this->id) {
            $this->subQuery->andWhere(Db::parseParam(
                'sprout_form_submissions.id', $this->id
            ));
        }

        if ($this->formHandle) {
            $this->query->andWhere(Db::parseParam(
                'sprout_forms.handle', $this->formHandle
            ));
        }

        if ($this->formName) {
            $this->query->andWhere(Db::parseParam(
                'sprout_forms.name', $this->formName
            ));
        }

        if ($this->statusId) {
            $this->subQuery->andWhere(Db::parseParam(
                'sprout_form_submissions.statusId', $this->statusId
            ));
        }

        $spamStatusId = FormsModule::getInstance()->submissionStatuses->getSpamStatusId();

        // If and ID is being requested directly OR the spam status ID OR
        // the spam status handle is explicitly provided, override the include spam flag
        if ($this->id || $this->statusId === $spamStatusId || $this->status === SubmissionStatus::SPAM_STATUS_HANDLE) {
            $this->excludeSpam = false;
        }

        if ($this->excludeSpam) {
            $this->subQuery->andWhere(Db::parseParam(
                'sprout_form_submissions.statusId', $spamStatusId, '!='
            ));
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return Db::parseParam('sprout_form_submissions_statuses.handle', $status);
    }

    protected function fieldLayouts(): array
    {
        $forms = FormsModule::getInstance()->forms->getAllForms();

        $layouts = [];

        foreach ($forms as $form) {
            $layouts[] = $form->getSubmissionFieldLayout();
        }

        return $layouts;
    }
}
