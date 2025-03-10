<?php

namespace BarrelStrength\Sprout\forms\components\formfields;

use BarrelStrength\Sprout\forms\components\elements\SubmissionElement;
use BarrelStrength\Sprout\forms\formfields\FormFieldInterface;
use BarrelStrength\Sprout\forms\formfields\FormFieldTrait;
use BarrelStrength\Sprout\forms\formfields\GroupLabel;
use Craft;
use craft\base\ElementInterface;
use craft\fields\Dropdown as CraftDropdown;
use craft\fields\Number as CraftNumber;
use craft\fields\PlainText as CraftPlainText;
use craft\helpers\Localization;
use craft\i18n\Locale;

class NumberFormField extends CraftNumber implements FormFieldInterface
{
    use FormFieldTrait;

    public string $cssClasses = '';

    /**
     * The size of the field
     */
    public ?int $size = null;

    public static function getGroupLabel(): string
    {
        return GroupLabel::label(GroupLabel::GROUP_COMMON);
    }

    public static function displayName(): string
    {
        return Craft::t('sprout-module-forms', 'Number');
    }

    public function init(): void
    {
        parent::init();

        // Normalize $max
        if ($this->max !== null && $this->max !== '0' && empty($this->max)) {
            $this->max = null;
        }

        // Normalize $min
        if ($this->min !== null && $this->min !== '0' && empty($this->min)) {
            $this->min = null;
        }

        // Normalize $decimals
        if (!$this->decimals) {
            $this->decimals = 0;
        }

        // Normalize $size
        if ($this->size !== null && $this->size === null) {
            $this->size = null;
        }
    }

    public function selectorIcon(): string
    {
        return 'hashtag';
    }

    public function getFieldInputFolder(): string
    {
        return 'number';
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // Is this a post request?
        $request = Craft::$app->getRequest();

        if (!$request->getIsConsoleRequest() && $request->getIsPost() && $value !== '') {
            // Normalize the number and make it look like this is what was posted
            $value = Localization::normalizeNumber($value);
        }

        return $value;
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('sprout-module-forms/_components/fields/Number/settings',
            [
                'field' => $this,
            ]
        );
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $decimals = $this->decimals;

        // If decimals is 0 (or null, empty for whatever reason), don't run this
        if ($decimals) {
            $decimalSeparator = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_DECIMAL_SEPARATOR);
            $value = number_format($value, $decimals, $decimalSeparator, '');
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/text', [
            'name' => $this->handle,
            'value' => $value,
            'size' => $this->size,
        ]);
    }

    public function getExampleInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('sprout-module-forms/_components/fields/Number/example',
            [
                'field' => $this,
            ]
        );
    }

    public function getFrontEndInputVariables($value, SubmissionElement $submission, array $renderingOptions = null): array
    {
        return [
            'name' => $this->handle,
            'value' => $value,
            //'field' => $this,
            //'submission' => $submission,
            'renderingOptions' => $renderingOptions,
            'min' => $this->min,
            'max' => $this->max,
            'decimals' => $this->decimals,
        ];
    }

    //public function getFrontEndInputHtml($value, SubmissionElement $submission, array $renderingOptions = null): Markup
    //{
    //    $rendered = Craft::$app->getView()->renderTemplate('number/input',
    //        [
    //            'name' => $this->handle,
    //            'value' => $value,
    //            'field' => $this,
    //            'submission' => $submission,
    //            'renderingOptions' => $renderingOptions,
    //        ]
    //    );
    //
    //    return TemplateHelper::raw($rendered);
    //}

    public function getCompatibleCraftFieldTypes(): array
    {
        return [
            CraftPlainText::class,
            CraftDropdown::class,
            CraftNumber::class,
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['min', 'max'], 'number'];
        $rules[] = [['decimals', 'size'], 'integer'];
        $rules[] = [
            ['max'],
            'compare',
            'compareAttribute' => 'min',
            'operator' => '>=',
        ];

        if (!$this->decimals) {
            $rules[] = [['min', 'max'], 'integer'];
        }

        return $rules;
    }
}
