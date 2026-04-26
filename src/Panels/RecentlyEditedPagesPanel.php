<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Panels;

use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Dashboard panel that shows the 10 most recently edited pages.
 *
 * Each row displays the page title and the date of the last modification.
 * The most recent entry appears first.
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Panels
 */
class RecentlyEditedPagesPanel extends DashboardPanel
{
    /**
     * Returns the translated panel title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return _t(__CLASS__ . '.TITLE', 'Recently edited pages');
    }

    /**
     * Returns the API endpoint for loading the panel content.
     *
     * URL example: /dashboard-api/recently-edited-pages
     *
     * @return string
     */
    public function getResourceURL(): string
    {
        return Director::absoluteURL('dashboard-api/recently-edited-pages');
    }

    /**
     * Returns the default settings for this panel.
     *
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'limit' => 10,
        ]);
    }

    /**
     * Returns the settings fields for this panel.
     *
     * @return FieldList
     */
    public function getSettingsFields(): FieldList
    {
        $fields = parent::getSettingsFields();

        /** @var NumericField $limitField */
        $limitField = NumericField::create('limit', _t(__CLASS__ . '.LIMIT', 'Number of entries'))
            ->setValue($this->getSetting('limit', 10));

        $fields->unshift($limitField);

        return $fields;
    }
}
