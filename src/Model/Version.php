<?php
declare(strict_types=1);

namespace App\Model;


use Chindit\Collection\Collection;
use Composer\Semver\Comparator;

final class Version implements \Stringable
{
    private string $version = '';
    private string $modifier = '';
    /** @var array|string[]  */
    private array $chunks = [];

    public function __construct(string $version)
    {
        $version = trim($version);
        $this->version = preg_replace('/[^0-9\.\*]/', '', $version) ?? '';
		if (empty($this->version)) {
			$this->version = $version;
		}
        $this->chunks = explode('.', $this->version);
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

    public function isGreaterThan(Version $version): bool
    {
        return Comparator::greaterThan($this->version, $version->getVersion());
    }

    public function __toString(): string
    {
        return $this->getVersion();
    }

    public function getSize(): int
    {
        return count($this->chunks);
    }

    public function chunkTo(int $size): string
    {
        return $this->modifier . match($size) {
            1 => $this->getMajor(),
            2 => implode('.', array_filter([$this->getMajor(), $this->getMinor()], fn($s) => $s !== '')),
            default => $this->getVersion()
            };
    }
}
