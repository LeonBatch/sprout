<?php

namespace BarrelStrength\Sprout\forms\components\formfields;

use BarrelStrength\Sprout\forms\components\elements\SubmissionElement;
use BarrelStrength\Sprout\forms\formfields\FormFieldInterface;
use BarrelStrength\Sprout\forms\formfields\FormFieldTrait;
use BarrelStrength\Sprout\forms\formfields\GroupLabel;
use Craft;
use craft\base\ElementInterface;
use craft\fields\Date as CraftDate;
use craft\fields\PlainText as CraftPlainText;
use craft\helpers\DateTimeHelper;
use craft\helpers\Template as TemplateHelper;
use DateTime;
use DateTimeInterface;
use yii\db\Schema;

class DateFormField extends CraftDate implements FormFieldInterface
{
    use FormFieldTrait;

    public string $cssClasses = '';

    // YYYY-MM-DD
    public ?string $minimumDate = null;

    // YYYY-MM-DD
    public ?string $maximumDate = null;

    public function __construct($config = [])
    {
        // dateTime => showDate + showTime
        if (isset($config['dateTime'])) {
            switch ($config['dateTime']) {
                case 'showBoth':
                    $config['showDate'] = true;
                    $config['showTime'] = true;
                    break;
                case 'showDate':
                    $config['showDate'] = true;
                    $config['showTime'] = false;
                    break;
                case 'showTime':
                    $config['showDate'] = false;
                    $config['showTime'] = true;
                    break;
            }

            unset($config['dateTime']);
        }

        parent::__construct($config);
    }

    public static function getGroupLabel(): string
    {
        return GroupLabel::label(GroupLabel::GROUP_COMMON);
    }

    public static function displayName(): string
    {
        return Craft::t('sprout-module-forms', 'Date/Time');
    }

    public static function dbType(): array|string
    {
        return Schema::TYPE_DATETIME;
    }

    public function init(): void
    {
        parent::init();

        // In case nothing is selected, default to the date.
        if (!$this->showDate && !$this->showTime) {
            $this->showDate = true;
        }
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): ?DateTimeInterface
    {
        if ($value && ($date = DateTimeHelper::toDateTime($value)) !== false) {
            return $date;
        }

        return null;
    }

    public function getExampleInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('sprout-module-forms/_components/fields/Date/example',
            [
                'field' => $this,
            ]
        );
    }

    public function selectorIcon(): string
    {
        return 'calendar';
    }

    public function getFieldInputFolder(): string
    {
        return 'date';
    }

    public function getSettingsHtml(): ?string
    {
        $dateTimeValue = null;

        // If they are both selected or nothing is selected, the select showBoth.
        if ($this->showDate && $this->showTime) {
            $dateTimeValue = 'showBoth';
        } elseif ($this->showDate) {
            $dateTimeValue = 'showDate';
        } elseif ($this->showTime) {
            $dateTimeValue = 'showTime';
        }

        $options = [15, 30, 60];
        $options = array_combine($options, $options);

        return Craft::$app->getView()->renderTemplate('sprout-module-forms/_components/fields/Date/settings',
            [
                'options' => [
                    [
                        'label' => Craft::t('sprout-module-forms', 'Show date'),
                        'value' => 'showDate',
                    ],
                    [
                        'label' => Craft::t('sprout-module-forms', 'Show time'),
                        'value' => 'showTime',
                    ],
                    [
                        'label' => Craft::t('sprout-module-forms', 'Show date and time'),
                        'value' => 'showBoth',
                    ],
                ],
                'value' => $dateTimeValue,
                'incrementOptions' => $options,
                'field' => $this,
            ]);
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        if ($this->minimumDate) {
            $this->minimumDate = Craft::$app->getView()->renderString($this->minimumDate);
        }

        if ($this->maximumDate) {
            $this->maximumDate = Craft::$app->getView()->renderString($this->maximumDate);
        }

        $rendered = Craft::$app->getView()->renderTemplate('sprout-module-forms/_components/fields/Date/input',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'timeOptions' => $this->getTimeIncrementsAsOptions($this->minuteIncrement),
            ]
        );

        return TemplateHelper::raw($rendered);
    }

    public function getFrontEndInputVariables($value, SubmissionElement $submission, array $renderingOptions = null): array
    {
        if ($this->minimumDate) {
            $this->minimumDate = Craft::$app->getView()->renderString($this->minimumDate);
        }

        if ($this->maximumDate) {
            $this->maximumDate = Craft::$app->getView()->renderString($this->maximumDate);
        }

        return [
            'name' => $this->handle,
            'value' => $value,
            //'field' => $this,
            //'submission' => $submission,
            'timeOptions' => $this->getTimeIncrementsAsOptions($this->minuteIncrement),
            'renderingOptions' => $renderingOptions,
            'showDate' => $this->showDate,
            'showTime' => $this->showTime,
            'minimumDate' => $this->minimumDate,
            'maximumDate' => $this->maximumDate,
        ];
    }

    //public function getFrontEndInputHtml($value, SubmissionElement $submission, array $renderingOptions = null): Markup
    //{
    //    if ($this->minimumDate) {
    //        $this->minimumDate = Craft::$app->getView()->renderString($this->minimumDate);
    //    }
    //
    //    if ($this->maximumDate) {
    //        $this->maximumDate = Craft::$app->getView()->renderString($this->maximumDate);
    //    }
    //
    //    $rendered = Craft::$app->getView()->renderTemplate('date/input',
    //        [
    //            'name' => $this->handle,
    //            'value' => $value,
    //            'field' => $this,
    //            'submission' => $submission,
    //            'timeOptions' => $this->getTimeIncrementsAsOptions($this->minuteIncrement),
    //            'renderingOptions' => $renderingOptions,
    //        ]
    //    );
    //
    //    return TemplateHelper::raw($rendered);
    //}

    /**
     * Prepare the time dropdown in increments of the selected minuteIncrement.
     */
    public function getTimeIncrementsAsOptions(int $minuteIncrement = 30, string $format = '', int $lower = 0, int $upper = 86400): array
    {
        $times = [];

        // Convert minute increment to seconds, 3600 seconds in a minute
        $step = 3600 * ($minuteIncrement / 60);

        if (empty($format)) {
            $format = 'g:i A';
        }

        $i = 0;
        foreach (range($lower, $upper, $step) as $increment) {
            $increment = gmdate('H:i', $increment);

            [$hour, $minutes] = explode(':', $increment);

            $date = new DateTime($hour . ':' . $minutes);

            $times[$i]['label'] = $date->format($format);
            $times[$i]['value'] = $increment;

            $i++;
        }

        return $times;
    }

    public function getCompatibleCraftFieldTypes(): array
    {
        return [
            CraftPlainText::class,
            CraftDate::class,
        ];
    }
}
