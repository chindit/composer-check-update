<?php
declare(strict_types=1);

namespace App\Model;



use Chindit\Collection\Collection;
use Composer\Semver\Comparator;

final class Package
{
    private string $packageName = '';
    private Version $composerVersion;
    private Version $packagistVersion;
    private ?Version $lowerBound = null;
    private ?Version $upperBound = null;
    private string $modifier = '';
    private const MAJOR_COLOR = 'red';
    private const MINOR_COLOR = 'blue';
    private const PATCH_COLOR = 'green';

    public function __construct(string $packageName = '', string $composerVersion = '', string $packagistVersion = '')
    {
        $this->packageName = $packageName;
        $this->modifier = $this->findModifier($composerVersion);
        $this->composerVersion = new Version($composerVersion);
        $this->packagistVersion = new Version($packagistVersion);
    }

    public function isUpdatable(): bool
    {
        return $this->packagistVersion->isGreaterThan($this->composerVersion)
            && $this->getActualVersionToString() !== $this->getNewVersionToString();
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
        return $this->modifier . $this->getActualVersion();
    }

    public function getNewVersionToString(): string
    {
        if ($this->getActualVersion()->getMinor() === '*' && $this->getNewVersion()->getMinor()) {
            return $this->modifier . implode('.', [$this->getNewVersion()->chunkTo(1), '*']);
        } elseif ($this->getActualVersion()->getPatch() === '*' && $this->getNewVersion()->getMinor()) {
            return $this->modifier . implode('.', [$this->getNewVersion()->chunkTo(2), '*']);
        }

        // Handle non-numeric versions
        if (!is_numeric($this->getActualVersion()->getMajor())) {
            if (is_numeric($this->getNewVersion()->getMajor())) {
                return '^' . $this->getNewVersion()->chunkTo(2);
            }
            return $this->getNewVersion()->getVersion();
        }
        return $this->modifier . $this->getNewVersion()->chunkTo($this->getActualVersion()->getSize());
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
        /**
         * Special SemVer case: if major is 0, all changes must be considered as major
         */
        return Comparator::greaterThan($this->getNewVersion()->getMajor(), $this->getActualVersion()->getMajor())
            || (int)$this->getActualVersion()->getMajor() === 0;
    }

    public function isMinorUpdate(): bool
    {
        return !$this->isMajorUpdate()
        && $this->getActualVersion()->getMinor() !== ''
        && $this->getActualVersion()->getMinor() !== '*'
        && $this->getNewVersion()->getMinor() !== ''
        && Comparator::greaterThan($this->getNewVersion()->getMinor(), $this->getActualVersion()->getMinor());
    }

    public function isPatchUpdate(): bool
    {
        return !$this->isMajorUpdate()
            && !$this->isMinorUpdate()
            && $this->getActualVersion()->getPatch() !== ''
            && $this->getActualVersion()->getPatch() !== '*'
            && $this->getNewVersion()->getPatch() !== ''
            && Comparator::greaterThan($this->getNewVersion()->getPatch(), $this->getActualVersion()->getPatch());
    }

    /**
     * Modifiers can be ~, ^, >= or < at the beginning
     * In the middle, it can be | or - followed with a beginning modifier
     */
    private function findModifier(string $version): string
    {
        // Version has multiple constrains
        if (str_contains($version, '|') || str_contains($version, ' - ')) {
            $versionRange = new Collection(str_contains($version, '|') ? explode('|', $version) : explode(' - ', $version));

            if ($versionRange->count() < 2) {
                throw new \InvalidArgumentException('Found less than two version while a boundary is present');
            }

            // If more than two versions, we ignore the third one
            $lower = $versionRange->first();
            $upper = $versionRange->get(1);
            if (is_string($lower) && is_string($upper)) {
                $this->lowerBound = new Version($lower);
                $this->upperBound = new Version($upper);
            }

            $version = $versionRange->get(1);
        }

        if (!is_string($version)) {
            $version = '';
        }

        // If first char is a digit, there is no modifier
        if (empty($version) || ctype_alnum($version[0])) {
            return '';
        }
        $matches = [];
        preg_match('/^(.+?)(?=\d).*/', $version, $matches);

        return empty($matches) ? '' : end($matches);
    }
}
