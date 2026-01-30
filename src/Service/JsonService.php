<?php

namespace App\Service;

use App\Exception\ComposerNotFoundException;
use App\Exception\InvalidComposerException;
use App\Model\Package;
use Chindit\Collection\Collection;

class JsonService
{
    /** @var array<string, mixed>  */
	private array $json;
	private string $composerPath;

	/**
	 * @throws InvalidComposerException
	 * @throws \JsonException
	 */
	public function __construct(string $composerPath)
	{
		$this->composerPath = $composerPath;
		$this->readComposer();
		$this->checkComposer();
	}

    /**
     * @return Collection<string, string>
     */
	public function getDependencies(): Collection
	{
        $data = $this->json['require'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        /** @var Collection<string, string> $collection */
        $collection = new Collection($data);
		return $this->removePhpAndExtensions($collection);
	}

    /**
     * @return Collection<string, string>
     */
	public function getDevDependencies(): Collection
	{
        $data = $this->json['require-dev'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        /** @var Collection<string, string> $collection */
        $collection = new Collection($data);
		return $this->removePhpAndExtensions($collection);
	}

	public function isWritable(): bool
	{
		return is_writable($this->composerPath);
	}

    /**
     * @param Collection|Package[] $updates
     * @return bool
     * @throws \JsonException
     */
	public function updateComposer(Collection $updates, string $level = 'all'): bool
	{
	    $packagesToUpdate = $updates->filter(function(Package $package) use ($level) {
            return match ($level) {
                'minor' => $package->isMinorUpdate() || $package->isPatchUpdate(),
                'patch' => $package->isPatchUpdate(),
                default => true,
            };
        })->keyBy(fn(Package $package) => $package->getName());

        $require = $this->json['require'] ?? [];
        if (is_array($require)) {
            foreach ($require as $packageName => $version) {
                if (is_string($packageName) && $packagesToUpdate->has($packageName)) {
                    /** @var Package $package */
                    $package = $packagesToUpdate->get($packageName);
                    $require[$packageName] = $package->getNewVersionToString();
                }
            }
            $this->json['require'] = $require;
        }

        $requireDev = $this->json['require-dev'] ?? [];
        if (is_array($requireDev)) {
            foreach ($requireDev as $packageName => $version) {
                if (is_string($packageName) && $packagesToUpdate->has($packageName)) {
                    /** @var Package $package */
                    $package = $packagesToUpdate->get($packageName);
                    $requireDev[$packageName] = $package->getNewVersionToString();
                }
            }
            $this->json['require-dev'] = $requireDev;
        }

		// Special support for Symfony
        /** @var Package|null $symfonyPackage */
        $symfonyPackage = $updates->filter(fn(Package $package) => str_starts_with($package->getName(), 'symfony/'))->first();
		if ($symfonyPackage && ($symfonyPackage->isMajorUpdate() || $symfonyPackage->isMinorUpdate())) {
            $extra = $this->json['extra'] ?? null;
            if (is_array($extra) && isset($extra['symfony']) && is_array($extra['symfony'])) {
                $symfonyExtra = $extra['symfony'];
                if (isset($symfonyExtra['require'])) {
                    $symfonyExtra['require'] = implode(
                        '.',
                        [$symfonyPackage->getNewVersion()->getMajor(), $symfonyPackage->getNewVersion()->getMinor(), '*']
                    );
                    $extra['symfony'] = $symfonyExtra;
                    $this->json['extra'] = $extra;
                }
            }
        }

		file_put_contents($this->composerPath, json_encode($this->json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		return true;
	}

	/**
	 * @throws \JsonException
	 */
	private function readComposer(): void
	{
		if (!str_ends_with($this->composerPath, 'composer.json')) {
			$this->composerPath .= '/composer.json';
		}

		if (!is_readable($this->composerPath)) {
			throw new ComposerNotFoundException($this->composerPath);
		}

        $content = file_get_contents($this->composerPath);
        if ($content === false) {
            throw new ComposerNotFoundException($this->composerPath);
        }

        $json = json_decode($content, true, 5,JSON_THROW_ON_ERROR);
        if (!is_array($json)) {
            throw new \JsonException('Invalid composer.json content: expected array');
        }

        /** @var array<string, mixed> $json */
		$this->json = $json;
	}

	/**
	 * @throws InvalidComposerException
	 */
	private function checkComposer(): void
	{
		if (!isset($this->json['require']) && !isset($this->json['require-dev'])) {
			throw new InvalidComposerException();
		}
	}

    /**
     * @param Collection<string, string> $dependencies
     * @return Collection<string, string>
     */
	private function removePhpAndExtensions(Collection $dependencies): Collection
	{
	    /** @var Collection<string, string> $filtered */
	    $filtered = $dependencies->filter(function(string $version, string $packageName)
        {
            return $packageName !== 'php'
                && !(str_starts_with($packageName, 'ext-') && !str_contains($packageName, '/'));
        });

        return $filtered;
	}
}
