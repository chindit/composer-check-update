<?php
declare(strict_types=1);

namespace App\Model;


use Chindit\Collection\Collection;
use Composer\Semver\Comparator;

final class Version implements \Stringable
{
    private string $version = '';
    private string $modifier = '';
    private string $alias = '';
    private string $stability = '';
    /** @var array|string[]  */
    private array $chunks = [];

    public function __construct(string $version)
    {
        $version = trim($version);
        // Handle alias 'as'
        if (str_contains($version, ' as ')) {
            [$this->version, $this->alias] = explode(' as ', $version, 2);
            $version = $this->alias;
        }

        // Handle stability '@'
        if (str_contains($version, '@')) {
            [$version, $this->stability] = explode('@', $version, 2);
        }

        $this->modifier = $this->findModifier($version);

        // Remove modifier to get the version part
        $cleanVersion = substr($version, strlen($this->modifier));

        // Remove 'v' prefix if present (e.g. v1.2.3)
        if (str_starts_with($cleanVersion, 'v') && strlen($cleanVersion) > 1 && is_numeric($cleanVersion[1])) {
            $cleanVersion = substr($cleanVersion, 1);
        }

        if (empty($this->version)) {
            $this->version = $cleanVersion;
        }

        // Get numeric part for chunks (handle 1.2.3-beta1 -> 1.2.3)
        $numericPart = '';
        preg_match('/^[0-9\.\*]+/', $cleanVersion, $matches);
        if (!empty($matches)) {
            $numericPart = $matches[0];
        }
        $this->chunks = explode('.', $numericPart);
    }

    private function findModifier(string $version): string
    {
        $version = trim($version);
        if (empty($version)) {
            return '';
        }
        $matches = [];
        if (preg_match('/^([~\^<>!=]+)/', $version, $matches)) {
            return $matches[1];
        }

        return '';
    }

    public function getMajor(): string
    {
        return (string)($this->chunks[0] ?? '');
    }

    public function getMinor(): string
    {
        return (string)($this->chunks[1] ?? '');
    }

    public function getPatch(): string
    {
        return (string)($this->chunks[2] ?? '');
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getStability(): string
    {
        return $this->stability;
    }

    public function getModifier(): string
    {
        return $this->modifier;
    }

    public function isGreaterThan(Version $version): bool
    {
        return Comparator::greaterThan($this->version, $version->getVersion());
    }

    public function __toString(): string
    {
        $str = $this->modifier . $this->getVersion();
        if (!empty($this->alias)) {
            $str = $this->version . ' as ' . $str;
        }
        if (!empty($this->stability)) {
            $str .= '@' . $this->stability;
        }
        return $str;
    }

    public function getSize(): int
    {
        if (empty($this->chunks) || $this->chunks[0] === '') {
            return 0;
        }
        return count($this->chunks);
    }

    public function chunkTo(int $size): string
    {
        $version = match($size) {
            1 => $this->getMajor(),
            2 => implode('.', array_filter([$this->getMajor(), $this->getMinor()], fn($s) => $s !== '')),
            default => $this->getVersion()
        };

        $str = $this->modifier . $version;
        if (!empty($this->alias)) {
            $str = $this->version . ' as ' . $str;
        }
        if (!empty($this->stability)) {
            $str .= '@' . $this->stability;
        }
        return $str;
    }
}
