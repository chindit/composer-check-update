<?php

namespace App\Service;

use App\Exceptions\ComposerNotFoundException;
use App\Exceptions\InvalidComposerException;

class JsonService
{
	private array $json;
	private string $composerPath;

	public function __construct(string $composerPath)
	{
		$this->composerPath = $composerPath;
		$this->readComposer();
		$this->checkComposer();
	}

	public function getDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require']);
	}

	public function getDevDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require-dev'] ?? []);
	}

	public function isWritable(): bool
	{
		return is_writable($this->composerPath);
	}

	public function updateComposer(array $updates): bool
	{
		$packagesToUpdate = [];
		foreach ($updates as $update) {
			$packagesToUpdate[$update[0]] = $update[2];
		}
		$packagesNamesToUpdate = array_keys($packagesToUpdate);

		foreach ($this->json['require'] as $package => $version) {
			if (in_array($package, $packagesNamesToUpdate, true)) {
				$this->json['require'][$package] = $packagesToUpdate[$package];
			}
		}

		foreach ($this->json['require-dev'] as $package => $version) {
			if (in_array($package, $packagesNamesToUpdate, true)) {
				$this->json['require-dev'][$package] = $packagesToUpdate[$package];
			}
		}

		file_put_contents($this->composerPath, json_encode($this->json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		return true;
	}

	private function readComposer(): void
	{
		if (!strpos($this->composerPath, 'composer.json')) {
			$this->composerPath .= '/composer.json';
		}

		if (!is_readable($this->composerPath)) {
			throw new ComposerNotFoundException($this->composerPath);
		}

		$this->json = json_decode(file_get_contents($this->composerPath), true, 2,JSON_THROW_ON_ERROR);
	}

	private function checkComposer(): void
	{
		if (!isset($this->json['require']) && !isset($this->json['require-dev'])) {
			throw new InvalidComposerException();
		}
	}

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
