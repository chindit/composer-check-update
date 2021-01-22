<?php
declare(strict_types=1);

namespace App\Model;


final class Version
{
    private string $version = '';
    private string $modifier = '';
    private array $chunks = [];

    public function __construct(string $version)
    {
        $this->version = preg_replace('[^0-9\.]', '', $version) ?? '';
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
}