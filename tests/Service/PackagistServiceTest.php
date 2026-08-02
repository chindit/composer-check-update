<?php

namespace App\Tests\Service;

use App\Service\PackagistService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackagistServiceTest extends TestCase
{
    public function testGetLastVersionFromResponse(): void
    {
        $mockClient = $this->createMock(HttpClientInterface::class);
        $service = new PackagistService($mockClient);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getLastVersionFromResponse');
        
        $packageData = [
            ['version' => '1.0.0'],
            ['version' => '1.1.0'],
            ['version' => '2.0.0-beta1'],
            ['version' => '2.0.0'],
            ['version' => 'dev-master'],
            ['version' => 'v1.2.3'],
            ['version' => 'v8.1.2'],
            ['version' => '2.0.7']
        ];
        
        $lastVersion = $method->invoke($service, $packageData);
        $this->assertEquals('v8.1.2', $lastVersion);
        
        $packageDataWithHigherBeta = [
            ['version' => '1.0.0'],
            ['version' => '2.0.0-beta1']
        ];
        $lastVersion = $method->invoke($service, $packageDataWithHigherBeta);
        $this->assertEquals('2.0.0-beta1', $lastVersion);
    }
}
