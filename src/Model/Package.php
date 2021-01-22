<?php
declare(strict_types=1);

namespace App\Model;


use App\Service\VersionService;

final class Package
{
    private string $packageName = '';
    private Version $composerVersion;
    private Version $packagistVersion;

    public function __construct(string $packageName = '', string $composerVersion = '', string $packagistVersion = '')
    {
        $this->packageName = $packageName;
        $this->composerVersion = new Version($composerVersion);
        $this->packagistVersion = new Version($packagistVersion);
    }

    public function isUpdatable(): bool
    {
        return VersionService::compare($this->packagistVersion, $this->composerVersion) === 1;
    }

    public function getName(): string
    {
        return $this->packageName;
    }

    public function getActualVersion(): Version
    {
        return $this->composerVersion;
    }

    public function getNewVersion(): Version
    {
        return $this->packagistVersion;
    }
}