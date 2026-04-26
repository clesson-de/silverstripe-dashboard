<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Extensions;

use Clesson\Silverstripe\Dashboard\Models\DashboardUserConfig;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\HasManyList;

/**
 * Extends Member with a has_many relation to DashboardUserConfig.
 *
 * @method HasManyList|DashboardUserConfig[] DashboardUserConfigs()
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Extensions
 */
class MemberExtension extends Extension
{
    /**
     * Has-many relations added to Member.
     *
     * @config
     */
    private static array $has_many = [
        'DashboardUserConfigs' => DashboardUserConfig::class,
    ];
}

