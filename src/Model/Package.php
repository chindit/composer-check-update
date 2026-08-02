<?php
declare(strict_types=1);

namespace App\Model;



use Chindit\Collection\Collection;

final class Package
{
    private string $packageName = '';
    private Version $composerVersion;
    private Version $packagistVersion;
    private ?Version $lowerBound = null;
    private ?Version $upperBound = null;
    private string $modifier = '';
    private string $boundary = '';
    private const MAJOR_COLOR = 'red';
    private const MINOR_COLOR = 'blue';
    private const PATCH_COLOR = 'green';

    public function __construct(string $packageName = '', string $composerVersion = '', string $packagistVersion = '')
    {
        $this->packageName = $packageName;
        $mainVersionString = $this->parseVersion($composerVersion);
        $this->composerVersion = new Version($mainVersionString);
        $this->packagistVersion = new Version($packagistVersion);
    }

    public function isUpdatable(): bool
    {
        return $this->packagistVersion->isGreaterThan($this->composerVersion);
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

    public function getActualVersionToString(): string
    {
        if ($this->hasLowerBound() && $this->hasUpperBound()) {
            return $this->getLowerBound() . $this->boundary . $this->getUpperBound();
        }
        return (string)$this->getActualVersion();
    }

    public function getNewVersionToString(): string
    {
        if ($this->getActualVersion()->getVersion() === '*') {
            return '*';
        }

        // Handle double version constraint
        if ($this->hasLowerBound() && $this->hasUpperBound()) {
            $newVersionPart = $this->formatNewVersionPart($this->getUpperBound(), $this->getNewVersion());
            return $this->getLowerBound() . $this->boundary . $newVersionPart;
        }

        $res = $this->formatNewVersionPart($this->getActualVersion(), $this->getNewVersion());

        if (!empty($this->modifier) && !str_starts_with($res, $this->modifier)) {
            $res = $this->modifier . $res;
        }

        return $res;
    }

    private function formatNewVersionPart(Version $actual, Version $new): string
    {
        $actualVersion = $actual->getVersion();
        if (str_contains($actualVersion, '*')) {
            if ($actualVersion === '*') {
                $res = '*';
            } else {
                $nbDotsActual = substr_count($actualVersion, '.');
                $newChunks = explode('.', $new->getVersion());
                $res = implode('.', array_slice($newChunks, 0, $nbDotsActual)) . '.*';
            }
        } else {
            $size = $actual->getSize();
            if ($size === 0) {
                $res = $new->getVersion();
            } else {
                $res = $new->chunkTo($size);
            }
        }

        // Apply modifier from actual if missing in result
        $actualModifier = $actual->getModifier();
        if ($actualModifier && !str_starts_with($res, $actualModifier)) {
            // Check if there is already a modifier in res
            if (!preg_match('/^[~\^<>!=]+/', $res)) {
                $res = $actualModifier . $res;
            }
        }

        if ($actual->getStability() && !$new->getStability() && !str_contains($res, '@')) {
            $res .= '@' . $actual->getStability();
        }

        if ($actual->getAlias() && !$new->getAlias() && !str_contains($res, ' as ')) {
            $res = $actual->getVersion() . ' as ' . $res;
        }

        return $res;
    }

    /**
     * @return array<int, string>
     */
    public function toTableArray(): array
    {
        $colorName = ($this->isMajorUpdate() ? self::MAJOR_COLOR : ($this->isMinorUpdate() ? self::MINOR_COLOR : self::PATCH_COLOR));

        $update = [];
        $update[0] = '<fg=' . $colorName . '>' . $this->getName() . '</>';
        $update[1] = '<fg=' . $colorName . '>' . $this->getActualVersionToString() . '</>';
        $update[2] = '<fg=' . $colorName . '>' . $this->getNewVersionToString() . '</>';

        return $update;
    }

    public function hasLowerBound(): bool
    {
        return $this->lowerBound !== null;
    }

    public function getLowerBound(): ?Version
    {
        return $this->lowerBound;
    }

    public function hasUpperBound(): bool
    {
        return $this->upperBound !== null;
    }

    public function getUpperBound(): ?Version
    {
        return $this->upperBound;
    }

    public function isMajorUpdate(): bool
    {
        $actualV = $this->getActualVersion();
        $newV = $this->getNewVersion();

        if ($actualV->getMajor() !== $newV->getMajor()) {
            return true;
        }

        if ($actualV->getMajor() === '0' && $actualV->getMinor() !== $newV->getMinor()) {
            return true;
        }

        return false;
    }

    public function isMinorUpdate(): bool
    {
        return !$this->isMajorUpdate()
        && $this->getActualVersion()->getMinor()
        && $this->getActualVersion()->getMinor() !== '*'
        && $this->getNewVersion()->getMinor()
        && $this->getActualVersion()->getMinor() < $this->getNewVersion()->getMinor();
    }

    public function isPatchUpdate(): bool
    {
        return !$this->isMajorUpdate()
            && !$this->isMinorUpdate()
            && $this->getActualVersion()->getPatch()
            && $this->getActualVersion()->getPatch() !== '*'
            && $this->getNewVersion()->getPatch()
            && $this->getActualVersion()->getPatch() < $this->getNewVersion()->getPatch();
    }

    /**
     * Parse version string and return the main version to be used for comparison
     */
    private function parseVersion(string $version): string
    {
        // Version has multiple constraints
        if (str_contains($version, '|') || str_contains($version, ' - ') || str_contains($version, ' ') || str_contains($version, ',')) {
            $separator = null;
            if (str_contains($version, '||')) {
                $separator = '||';
            } elseif (str_contains($version, '|')) {
                $separator = '|';
            } elseif (str_contains($version, ' - ')) {
                $separator = ' - ';
            } elseif (str_contains($version, ',')) {
                $separator = ',';
            } elseif (preg_match('/\s+/', trim($version)) && !str_contains($version, ' as ')) {
                $separator = ' ';
            }

            if ($separator !== null) {
                $versionRange = new Collection(explode($separator, $version));
                $versionRange = $versionRange->map(fn($v) => trim($v))->filter(fn($v) => !empty($v));

                if ($versionRange->count() >= 2) {
                    $this->lowerBound = new Version($versionRange->first());
                    $this->upperBound = new Version($versionRange->get($versionRange->count() - 1));

                    $this->boundary = ($separator === ' ' || $separator === ' - ') ? $separator : ' ' . trim($separator) . ' ';
                    if ($separator === ',') {
                        $this->boundary = ', ';
                    }

                    $version = $versionRange->get($versionRange->count() - 1);
                }
            }
        }

        $this->modifier = $this->extractModifier($version);

        return $version;
    }

    private function extractModifier(string $version): string
    {
        $trimmedVersion = trim($version);
        if (empty($trimmedVersion)) {
            return '';
        }
        $matches = [];
        if (preg_match('/^([~\^<>!=]+)/', $trimmedVersion, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
