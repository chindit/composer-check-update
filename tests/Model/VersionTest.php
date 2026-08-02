<?php

namespace App\Tests\Model;

use App\Model\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testVersionParsing(): void
    {
        $version = new Version('^1.2.3');
        $this->assertEquals('1.2.3', $version->getVersion());
        $this->assertEquals('^', (string) (new \ReflectionClass($version))->getProperty('modifier')->getValue($version));
        $this->assertEquals('1', $version->getMajor());
        $this->assertEquals('2', $version->getMinor());
        $this->assertEquals('3', $version->getPatch());

        $version = new Version('v2.0.1');
        $this->assertEquals('2.0.1', $version->getVersion());
        $this->assertEquals('2', $version->getMajor());
    }

    public function testStability(): void
    {
        $v1 = new Version('1.0.0-beta1');
        $v2 = new Version('1.0.0');
        
        $this->assertTrue($v2->isGreaterThan($v1));
        $this->assertFalse($v1->isGreaterThan($v2));
        $this->assertEquals('1.0.0-beta1', $v1->getVersion());
    }

    public function testDevVersions(): void
    {
        $v1 = new Version('dev-master');
        $this->assertEquals('dev-master', $v1->getVersion());
        $this->assertEquals('', $v1->getMajor());
    }

    public function testComparison(): void
    {
        $this->assertTrue((new Version('1.1.0'))->isGreaterThan(new Version('1.0.9')));
        $this->assertTrue((new Version('2.0.0'))->isGreaterThan(new Version('1.9.9')));
        $this->assertTrue((new Version('1.0.1'))->isGreaterThan(new Version('1.0.0')));
    }
}
