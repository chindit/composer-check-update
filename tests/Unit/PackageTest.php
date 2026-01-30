<?php

use App\Model\Package;

it('correctly identifies updatable packages', function (string $current, string $latest, bool $expected) {
    $package = new Package('foo/bar', $current, $latest);
    expect($package->isUpdatable())->toBe($expected);
})->with([
    'newer patch' => ['1.0.0', '1.0.1', true],
    'newer minor' => ['1.0.0', '1.1.0', true],
    'newer major' => ['1.0.0', '2.0.0', true],
    'same version' => ['1.0.0', '1.0.0', false],
    'older version' => ['1.1.0', '1.0.0', false],
    'with caret' => ['^1.0.0', '1.1.0', true],
    'with tilde' => ['~1.0.0', '1.0.1', true],
    'range with hyphen' => ['1.0.0 - 2.0.0', '2.1.0', true],
    'range with pipe' => ['1.0.0|2.0.0', '2.1.0', true],
    'with v prefix' => ['v1.0.0', '1.1.0', true],
    'greater than or equal' => ['>=1.0.0', '1.1.0', true],
    'dev-main' => ['dev-main', '1.0.0', true],
	'dev-latest' => ['dev-latest', 'dev-latest', false],
]);

it('correctly formats the new version string', function (string $current, string $latest, string $expected) {
    $package = new Package('foo/bar', $current, $latest);
    expect($package->getNewVersionToString())->toBe($expected);
})->with([
    'caret update' => ['^1.0.0', '1.1.0', '^1.1.0'],
    'tilde update' => ['~1.0.0', '1.0.1', '~1.0.1'],
    'no modifier' => ['1.0.0', '1.1.0', '1.1.0'],
    'wildcard minor' => ['1.*', '2.1.0', '2.*'],
    'wildcard patch' => ['1.1.*', '1.2.1', '1.2.*'],
    'dev-master' => ['dev-master', '1.0.0', '^1.0'],
    'dev-main' => ['dev-main', '1.2.3', '^1.2'],
    'dev-develop' => ['dev-develop', '2.0.1', '^2.0'],
    'dev-feature/branch' => ['dev-feature/branch', '3.2.1', '^3.2'],
    'dev-latest to dev-latest' => ['dev-latest', 'dev-latest', 'dev-latest'],
]);

it('correctly identifies update types', function (string $current, string $latest, bool $isMajor, bool $isMinor, bool $isPatch) {
    $package = new Package('foo/bar', $current, $latest);
    expect($package->isMajorUpdate())->toBe($isMajor)
        ->and($package->isMinorUpdate())->toBe($isMinor)
        ->and($package->isPatchUpdate())->toBe($isPatch);
})->with([
    'major update' => ['1.0.0', '2.0.0', true, false, false],
    'minor update' => ['1.0.0', '1.1.0', false, true, false],
    'patch update' => ['1.0.0', '1.0.1', false, false, true],
    'semver special 0.x' => ['0.1.0', '0.1.1', true, false, false],
    'dev-main to major' => ['dev-main', '1.0.0', true, false, false],
]);

it('does not report update when formatted versions are identical', function (string $current, string $latest) {
    $package = new Package('foo/bar', $current, $latest);
    expect($package->isUpdatable())->toBeFalse();
})->with([
    'caret same precision' => ['^3.7', '3.7.0'],
    'caret already updated' => ['^12.49', '12.49.0'],
    'no modifier same precision' => ['1.2', '1.2.0'],
]);
