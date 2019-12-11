<?php

namespace App\Service;

use App\Exceptions\InvalidPackageException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackagistService
{
	/**
	 * @var HttpClientInterface
	 */
	private $httpClient;

	public function __construct(HttpClientInterface $httpClient)
	{
		$this->httpClient = $httpClient;
	}

	public function checkUpdate(string $dependency): string
	{
		try
		{
			$request = $this->httpClient->request('GET', sprintf('https://repo.packagist.org/p/%s.json', $dependency));

			if ($request->getStatusCode() >= 400) {
				throw new InvalidPackageException(sprintf('«%s» is not a valid composer package', $dependency));
			}

			$response = $request->toArray();

			if (!is_array($response['packages']) || !isset($response['packages'][$dependency])) {
				throw new InvalidPackageException(sprintf('«%s» is not a valid composer package', $dependency));
			}

			return $this->getLastVersionFromResponse($response['packages'][$dependency]);
		} catch (TransportExceptionInterface $exception) {
			throw new InvalidPackageException($exception->getMessage());
		}
	}

	public function getLastVersionFromResponse(array $package): string
	{
		$versions = preg_grep('/^v?[0-9]+.[0-9]+(.[0-9]+)?(.[0-9]+)?$/', array_keys($package));

		return max($versions);
	}

	public function needsUpdate(string $lastVersion, string $composerVersion): string
	{
		// Remove starting 'v'
		$lastVersion = str_replace('v', '', $lastVersion);
		$hasUpperBound = strpos($composerVersion, '^') === 0;
		$hasEqualBound = strpos($composerVersion, '~') === 0;
		// Remove starting '^' for composer version
		$composerVersion = str_replace('^', '', $composerVersion);
		$composerVersion = str_replace('~', '', $composerVersion);

		$chunks = substr_count($composerVersion, '.') + 1;
		$lastVersionChunks = substr_count($lastVersion, '.') + 1;

		if ($lastVersionChunks > $chunks) {
			$lastVersion = substr($lastVersion, 0, strrpos($lastVersion, '.'));
		}

		if (version_compare($lastVersion, $composerVersion, '>')) {
			return $hasUpperBound ? ('^'.$lastVersion) : ($hasEqualBound ? ('~'.$lastVersion) : $lastVersion);
		} else {
			return '';
		}
	}
}
