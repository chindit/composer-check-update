<?php

namespace App\Service;

use App\Exception\InvalidPackageException;
use Composer\Semver\Comparator;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackagistService
{
	private HttpClientInterface $httpClient;

	public function __construct(HttpClientInterface $httpClient)
	{
		$this->httpClient = $httpClient;
	}

	public function checkPackage(string $dependency): string
	{
		try
		{
			$request = $this->httpClient->request('GET', sprintf('https://repo.packagist.org/p2/%s.json', $dependency));

			if ($request->getStatusCode() >= 400) {
				throw new InvalidPackageException(sprintf('«%s» is not a valid composer package', $dependency));
			}

			$response = $request->toArray();

			if (!is_array($response['packages']) || !isset($response['packages'][$dependency])) {
				throw new InvalidPackageException(sprintf('«%s» is not a valid composer package', $dependency));
			}

			$packageData = $response['packages'][$dependency];
            if (!is_array($packageData)) {
                throw new InvalidPackageException('Invalid package data from Packagist');
            }

            /** @var array<int, array<string, string>> $normalizedPackageData */
            $normalizedPackageData = [];
            foreach ($packageData as $key => $val) {
                if (is_int($key) && is_array($val)) {
                    $item = [];
                    foreach ($val as $k => $v) {
                        if (is_string($k) && is_string($v)) {
                            $item[$k] = $v;
                        }
                    }
                    $normalizedPackageData[$key] = $item;
                }
            }

			return $this->getLastVersionFromResponse($normalizedPackageData);
		} catch (TransportExceptionInterface $exception) {
			throw new InvalidPackageException($exception->getMessage());
		}
	}

    /**
     * @param array<int, array<string, string>> $package
     * @return string
     * @throws InvalidPackageException
     */
	private function getLastVersionFromResponse(array $package): string
	{
        if (!isset($package[0]['version_normalized'])) {
            throw new InvalidPackageException('Invalid package data from Packagist');
        }

		return implode('.', array_slice(explode('.', $package[0]['version_normalized']), 0, 3));
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

		if (Comparator::greaterThan($lastVersion, $composerVersionCleaned)) {
            return $this->findVersionPattern($composerVersion, $lastVersion);
		}

		return '';
	}

    /**
     * TODO Improve and return only modifier
     * https://getcomposer.org/doc/articles/versions.md#writing-version-constraints
     */
	private function findVersionPattern(string $composerVersion, string $lastVersion): string
	{
		$hasUpperBound = str_starts_with($composerVersion, '^');
		$hasEqualBound = str_starts_with($composerVersion, '~');

		// Check if star operator is present
		if (str_contains($composerVersion, '*')) {
			// Use star operator for new version
			$nbChunksBeforeStar = substr_count($composerVersion, '.');
			// Total of chunks — 1
			$nbChunksNewVersion = substr_count($lastVersion, '.');

			if ($nbChunksBeforeStar === $nbChunksNewVersion) {
                $lastDot = strrpos($lastVersion, '.');
				$lastVersion = ($lastDot !== false ? substr($lastVersion, 0, $lastDot) : $lastVersion) . '.*';
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
