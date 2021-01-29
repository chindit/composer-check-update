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
     * @return string[]
     */
	public function getDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require']);
	}

    /**
     * @return string[]
     */
	public function getDevDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require-dev'] ?? []);
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
	public function updateComposer(Collection $updates): bool
	{
	    $packagesToUpdate = $updates->keyBy(fn(Package $package) => $package->getName());

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

		file_put_contents($this->composerPath, json_encode($this->json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		return true;
	}

	/**
	 * @throws \JsonException
	 */
	private function readComposer(): void
	{
		if (!strpos($this->composerPath, 'composer.json')) {
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
     * @param array<string, string> $dependencies
     * @return array<string, string>
     */
	private function removePhpAndExtensions(array $dependencies): array
	{
		$keys = array_keys($dependencies);

		foreach ($keys as $key) {
			if ($key === 'php' || (stripos($key, 'ext-') === 0 && strpos($key, '/') === false)) {
				unset($dependencies[$key]);
			}
		}

		return $dependencies;
	}
}
