<?php

namespace App\Service;

use App\Exception\ComposerNotFoundException;
use App\Exception\InvalidComposerException;
use App\Model\Package;
use Chindit\Collection\Collection;

class JsonService
{
    /** @var array<string, array<string, string> >  */
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
		return $this->removePhpAndExtensions(new Collection($this->json['require']));
	}

    /**
     * @return Collection<string, string>
     */
	public function getDevDependencies(): Collection
	{
		return $this->removePhpAndExtensions(new Collection($this->json['require-dev'] ?? []));
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

		foreach ($this->json['require'] as $packageName => $version) {
			if ($packagesToUpdate->has($packageName)) {
			    /** @var Package $package */
			    $package = $packagesToUpdate->get($packageName);
				$this->json['require'][$packageName] = $package->getNewVersionToString();
			}
		}

		foreach ($this->json['require-dev'] as $packageName => $version) {
            if ($packagesToUpdate->has($packageName)) {
                /** @var Package $package */
                $package = $packagesToUpdate->get($packageName);
                $this->json['require-dev'][$packageName] = $package->getNewVersionToString();
            }
		}

		// Special support for Symfony
        /** @var Package|null $symfonyPackage */
        $symfonyPackage = $packagesToUpdate->filter(fn(Package $package) => str_starts_with($package->getName(), 'symfony/'))->first();
		if ($symfonyPackage && ($symfonyPackage->isMajorUpdate() || $symfonyPackage->isMinorUpdate())) {
            if (isset($this->json['extra']) && $this->json['extra']['symfony'] && $this->json['extra']['symfony']['require']) {
                $this->json['extra']['symfony']['require'] = implode(
                    '.',
                    [$symfonyPackage->getNewVersion()->getMajor(), $symfonyPackage->getNewVersion()->getMinor(), '*']
                );
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

		$this->json = json_decode(file_get_contents($this->composerPath), true, 5,JSON_THROW_ON_ERROR);
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
	    return $dependencies->filter(function(string $version, string $packageName)
        {
            return $packageName !== 'php'
                && !(str_starts_with($packageName, 'ext-') && !str_contains($packageName, '/'));
        });
	}
}
