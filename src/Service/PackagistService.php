<?php

namespace App\Service;

use App\Exceptions\InvalidPackageException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackagistService
{
	private HttpClientInterface $httpClient;

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

		if (empty($versions)) {
			return '';
		}

		rsort($versions, SORT_NATURAL);

		return $versions[0];
	}

	public function needsUpdate(string $lastVersion, string $composerVersion): string
	{
		// Remove starting 'v'
		$lastVersion = str_replace('v', '', $lastVersion);

		// Remove starting '^' for composer version
		$composerVersionCleaned = $composerVersion;
		$composerVersionCleaned = str_replace(['^', '~', '.*', '*'], '', $composerVersionCleaned);

		$chunksNumber = substr_count($composerVersionCleaned, '.') + 1;
		$lastVersionChunksNumber = substr_count($lastVersion, '.') + 1;

		if ($lastVersionChunksNumber > $chunksNumber) {
			$lastVersionChunks = explode('.', $lastVersion);
			$lastVersion = implode('.', array_slice($lastVersionChunks, 0, $chunksNumber));
		}

		/**
		 * Output for version_compare is
		 * -1 if $lastVersion is OLDER (smaller) THAN $composerVersionCleaned
		 * 0 if $lastVersion is EQUAL TO $composerVersionCleaned
		 * 1 if $lastVersion is NEWER (greater) THAN $composerVersionCleaned
		 */
		if (version_compare($lastVersion, $composerVersionCleaned) === 1) {
			return $this->findVersionPattern($composerVersion, $lastVersion);
		}

		return '';
	}

	private function findVersionPattern(string $composerVersion, string $lastVersion): string
	{
		$hasUpperBound = strpos($composerVersion, '^') === 0;
		$hasEqualBound = strpos($composerVersion, '~') === 0;

		// Check if star operator is present
		if (strpos($composerVersion, '*') !== false) {
			// Use star operator for new version
			$nbChunksBeforeStar = substr_count($composerVersion, '.');
			// Total of chunks — 1
			$nbChunksNewVersion = substr_count($lastVersion, '.');

			if ($nbChunksBeforeStar === $nbChunksNewVersion) {
				$lastVersion = substr($lastVersion, 0, strrpos($lastVersion, '.')) . '.*';
			} else {
				$chunks = explode('.', $lastVersion);
				$chunks = array_slice($chunks, 0, $nbChunksBeforeStar);
				$chunks[] = '*';
				$lastVersion = implode('.', $chunks);
			}
		}

		// Check if number of chunks are equals or not
		if (substr_count($composerVersion, '.') < substr_count($lastVersion, '.')) {
			$chunks = explode('.', $lastVersion);
			$chunks = array_slice($chunks, 0, substr_count($composerVersion, '.') + 1);
			$lastVersion = implode('.', $chunks);
		}

		if ($hasUpperBound) {
			return '^'.$lastVersion;
		}
		if ($hasEqualBound) {
			return '~'.$lastVersion;
		}

		return $lastVersion;
	}
}
