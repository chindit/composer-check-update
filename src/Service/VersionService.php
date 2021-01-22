<?php
declare(strict_types=1);

namespace App\Service;


use App\Model\Version;

class VersionService
{
    /**
     * Output for version_compare is
     * -1 if $lastVersion is OLDER (smaller) THAN $composerVersionCleaned
     * 0 if $lastVersion is EQUAL TO $composerVersionCleaned
     * 1 if $lastVersion is NEWER (greater) THAN $composerVersionCleaned
     */
    public static function compare(Version $source, Version $target): int
    {
        return version_compare($source->getVersion(), $target->getVersion());
    }
}