<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Panels;

use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Dashboard panel that shows the most recently uploaded files.
 *
 * Each row displays the filename and the upload date.
 * Clicking a file opens it in the AssetAdmin.
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Panels
 */
class RecentlyUploadedFilesPanel extends DashboardPanel
{
    /**
     * Returns the translated panel title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return _t(__CLASS__ . '.TITLE', 'Recently uploaded files');
    }

    /**
     * Returns the API endpoint for loading the panel content.
     *
     * URL example: /dashboard-api/recently-uploaded-files
     *
     * @return string
     */
    public function getResourceURL(): string
    {
        return Director::absoluteURL('dashboard-api/recently-uploaded-files');
    }

    /**
     * Returns the default settings for this panel.
     *
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'limit' => 5,
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
            ->setValue($this->getSetting('limit', 5));

        $fields->unshift($limitField);

        return $fields;
    }
}
