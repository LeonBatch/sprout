<?php

namespace BarrelStrength\Sprout\core\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Deletes target elements from the database
 */
class PurgeElements extends BaseJob
{
    public ?string $elementType = null;

    public ?int $siteId = null;

    public array $idsToDelete = [];

    public array $idsToExclude = [];

    public function execute($queue): void
    {
        $totalSteps = count($this->idsToDelete);

        foreach ($this->idsToDelete as $key => $id) {
            $step = $key + 1;
            $this->setProgress($queue, $step / $totalSteps);

            $element = Craft::$app->getElements()->getElementById($id, $this->elementType, $this->siteId);

            if ($element && !Craft::$app->getElements()->deleteElement($element, true)) {
                Craft::error('Unable to delete the ' . $this->elementType . ' element type using ID:' . $id, __METHOD__);
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('sprout-module-core', 'Deleting oldest ' . $this->elementType);
    }
}
