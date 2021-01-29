<?php
declare(strict_types=1);

namespace App\Model;


use Chindit\Collection\Collection;

final class Version implements \Stringable
{
    private string $version = '';
    private string $modifier = '';
    /** @var array|string[]  */
    private array $chunks = [];

    public function __construct(string $version)
    {
        $version = trim($version);
        $this->version = preg_replace('/[^0-9\.]/', '', $version) ?? '';
        $this->chunks = explode('.', $this->version);
    }

    public function getMajor(): string
    {
        return $this->chunks[0] ?? '';
    }

    public function getMinor(): string
    {
        return $this->chunks[1] ?? '';
    }

    public function getPatch(): string
    {
        return $this->chunks[2] ?? '';
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isGreaterThan(Version $version): bool
    {
        return $this->getMajor() > $version->getMajor()
            || ($this->getMinor() && $version->getMinor() && $this->getMinor() > $version->getMinor())
            || ($this->getPatch() && $version->getPatch() && $this->getPatch() > $version->getPatch());
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
            2 => implode('.', [$this->getMajor(), $this->getMinor()]),
            default => $this->getVersion()
            };
    }
}