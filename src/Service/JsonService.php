<?php

namespace App\Service;

use App\Exceptions\ComposerNotFoundException;
use App\Exceptions\InvalidComposerException;

class JsonService
{
	private $json;

	public function __construct(string $composerPath)
	{
		$this->readComposer($composerPath);
		$this->checkComposer();
	}

	public function getDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require']);
	}

	public function getDevDependencies(): array
	{
		return $this->removePhpAndExtensions($this->json['require-dev']);
	}

	private function readComposer(string $composerPath): void
	{
		if (!strpos($composerPath, 'composer.json')) {
			$composerPath .= '/composer.json';
		}

		if (!is_readable($composerPath)) {
			throw new ComposerNotFoundException($composerPath);
		}

		$this->json = json_decode(file_get_contents($composerPath), true);
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
