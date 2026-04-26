<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Stores the dashboard panel configuration for a specific user.
 *
 * Each record represents one panel in a user's dashboard layout,
 * including its position (sort), width, refresh interval, and visibility.
 *
 * @property string $DashboardKey
 * @property string $PanelClass
 * @property int $Sort
 * @property string $Width
 * @property int $RefreshInterval
 * @property bool $Visible
 * @property string $Settings
 * @property int $MemberID
 * @method Member Member()
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Models
 */
class DashboardUserConfig extends DataObject
{
    /**
     * Database table name.
     *
     * @config
     */
    private static string $table_name = 'Dashboard_DashboardUserConfig';

    /**
     * Database fields.
     *
     * @config
     */
    private static array $db = [
        'DashboardKey' => 'Varchar(100)',
        'PanelClass' => 'Varchar(255)',
        'Sort' => 'Int',
        'Width' => 'Varchar(10)',
        'RefreshInterval' => 'Int',
        'Visible' => 'Boolean(1)',
        'Settings' => 'Text',
    ];

    /**
     * Has-one relations.
     *
     * @config
     */
    private static array $has_one = [
        'Member' => Member::class,
    ];

    /**
     * Default sort order.
     *
     * @config
     */
    private static string $default_sort = 'Sort ASC';

    /**
     * General search field for CMS search.
     *
     * @config
     */
    private static string $general_search_field = 'DashboardKey';

    /**
     * Default field values.
     *
     * @config
     */
    private static array $defaults = [
        'Visible' => true,
        'Width' => 'single',
        'RefreshInterval' => 0,
        'Sort' => 0,
    ];

    /**
     * Summary fields for GridField display.
     *
     * @config
     */
    private static array $summary_fields = [
        'DashboardKey',
        'PanelClass',
        'Sort',
        'Width',
        'Visible',
    ];

    /**
     * Only the owning member or an administrator may view this record.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        $member = $member ?? Security::getCurrentUser();

        if ($member === null) {
            return false;
        }

        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }

        return $this->MemberID === $member->ID;
    }

    /**
     * Only the owning member or an administrator may edit this record.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        $member = $member ?? Security::getCurrentUser();

        if ($member === null) {
            return false;
        }

        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }

        return $this->MemberID === $member->ID;
    }

    /**
     * Only logged-in members or administrators may create records.
     *
     * @param Member|null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        $member = $member ?? Security::getCurrentUser();

        return $member !== null;
    }

    /**
     * Only the owning member or an administrator may delete this record.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        $member = $member ?? Security::getCurrentUser();

        if ($member === null) {
            return false;
        }

        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }

        return $this->MemberID === $member->ID;
    }
}


