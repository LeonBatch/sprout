<?php

namespace BarrelStrength\Sprout\forms\components\elements\actions;

use Craft;
use craft\base\ElementAction;

class ChangeFormType extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('sprout-module-forms', 'Change Form Type');
    }

    public static function isDestructive(): bool
    {
        return true;
    }

    /**
     * @noinspection UnterminatedStatementJS UnterminatedStatementJS
     * @noinspection JSUnnecessarySemicolon JSUnnecessarySemicolon
     */
    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        validateSelection: \$selectedItems => Garnish.hasAttr(\$selectedItems.find('.element'), 'data-savable'),
        activate: \$selectedItems => {
            const elementIds = \$selectedItems.map((index, element) => {
                return $(element).data('id');
            }).get();
            const slideout = new  Craft.CpScreenSlideout('sprout-module-forms/form-types/change-form-type-slideout', {
                params: {
                    elementIds: elementIds,
                },
            });
            slideout.on('submit', function() {
                Craft.elementIndex.updateElements();
            });
        },
    });
})();
JS, [static::class]);

        return null;
    }
}
