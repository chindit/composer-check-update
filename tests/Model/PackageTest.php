<?php

namespace App\Tests\Model;

use App\Model\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public function testDoubleConstraintParsing(): void
    {
        $package = new Package('test/pkg', '^7.0 || ^8.0', '9.0.0');
        $this->assertEquals('^7.0 || ^8.0', $package->getActualVersionToString());
        $this->assertEquals('^7.0 || ^9.0', $package->getNewVersionToString());
        $this->assertTrue($package->isUpdatable());
        $this->assertTrue($package->isMajorUpdate());
    }

    public function testRangeConstraintParsing(): void
    {
        $package = new Package('test/pkg', '7.0 - 8.0', '9.0.0');
        $this->assertEquals('7.0 - 8.0', $package->getActualVersionToString());
        $this->assertEquals('7.0 - 9.0', $package->getNewVersionToString());
    }

    public function testWildcardConstraint(): void
    {
        $package = new Package('test/pkg', '7.* || 8.*', '9.1.0');
        $this->assertEquals('7.* || 8.*', $package->getActualVersionToString());
        $this->assertEquals('7.* || 9.*', $package->getNewVersionToString());
    }

    public function testUpdatable(): void
    {
        $package = new Package('test/pkg', '^1.0.0', '1.1.0');
        $this->assertTrue($package->isUpdatable());
        
        $package = new Package('test/pkg', '^1.1.0', '1.1.0');
        $this->assertFalse($package->isUpdatable());
        
        $package = new Package('test/pkg', '^1.2.0', '1.1.0');
        $this->assertFalse($package->isUpdatable());
    }

    public function testDevVersionUpdate(): void
    {
        $package = new Package('test/pkg', 'dev-master', 'dev-develop');
        $this->assertEquals('dev-master', $package->getActualVersionToString());
        $this->assertEquals('dev-develop', $package->getNewVersionToString());
    }

    public function testVPrefixNormalization(): void
    {
        $package = new Package('test/pkg', 'v1.0.0', '1.1.0');
        $this->assertEquals('1.0.0', $package->getActualVersionToString());
        $this->assertEquals('1.1.0', $package->getNewVersionToString());
    }

    public function testComposerSupportExactVersion(): void
    {
        $package = new Package('vendor/package', '1.2.3', '1.2.4');
        $this->assertEquals('1.2.3', $package->getActualVersionToString());
        $this->assertEquals('1.2.4', $package->getNewVersionToString());
    }

    public function testComposerSupportRanges(): void
    {
        $package = new Package('vendor/package', '>=1.0 <1.1 || >=1.2', '1.3.0');
        $this->assertEquals('>=1.0 <1.1 || >=1.3', $package->getNewVersionToString());

        $package = new Package('vendor/package', '>=1.0 <2.0', '2.1.0');
        $this->assertEquals('>=1.0 <2.1', $package->getNewVersionToString());
    }

    public function testComposerSupportWildcard(): void
    {
        $package = new Package('vendor/package', '1.0.*', '1.1.0');
        $this->assertEquals('1.1.*', $package->getNewVersionToString());

        $package = new Package('vendor/package', '*', '2.0.0');
        $this->assertEquals('*', $package->getNewVersionToString());
    }

    public function testComposerSupportTildeAndCaret(): void
    {
        $package = new Package('vendor/package', '~1.2', '2.0.0');
        $this->assertEquals('~2.0', $package->getNewVersionToString());

        $package = new Package('vendor/package', '^1.2.3', '2.0.0');
        $this->assertEquals('^2.0.0', $package->getNewVersionToString());

        $package = new Package('vendor/package', '^0.3', '0.4.0');
        $this->assertEquals('^0.4', $package->getNewVersionToString());
        $this->assertTrue($package->isMajorUpdate());
    }

    public function testComposerSupportAlias(): void
    {
        $package = new Package('vendor/package', 'dev-main as 1.0.0', '1.1.0');
        $this->assertEquals('dev-main as 1.1.0', $package->getNewVersionToString());
    }

    public function testComposerSupportStability(): void
    {
        $package = new Package('vendor/package', '1.0.0-beta2', '1.0.0-RC1');
        $this->assertTrue($package->isUpdatable());
        $this->assertEquals('1.0.0-RC1', $package->getNewVersionToString());

        $package = new Package('vendor/package', '^1.0.0@beta', '1.1.0');
        $this->assertEquals('^1.1.0@beta', $package->getNewVersionToString());
    }
}
