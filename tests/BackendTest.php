<?php

namespace Cm\MongoSession\Tests;

use Cm\MongoSession\Backend;
use PHPUnit\Framework\TestCase;

class BackendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCalculateLifetimeForBots(): void
    {
        $backend = new class extends Backend
        {
            public function __construct() {} // Skip parent constructor

            public function calculateLifetime(int $reads, string $userAgent): int
            {
                return $this->_calculateLifetime($reads, $userAgent);
            }
        };

        $botUserAgent = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
        $result = $backend->calculateLifetime(10, $botUserAgent);

        $this->assertEquals(30, $result); // BOT_LIFETIME constant
    }

    public function testCalculateLifetimeForRegularUsers(): void
    {
        $backend = new class extends Backend
        {
            public function __construct() {} // Skip parent constructor

            public function calculateLifetime(int $reads, string $userAgent): int
            {
                return $this->_calculateLifetime($reads, $userAgent);
            }
        };

        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // Test with low number of reads
        $result = $backend->calculateLifetime(2, $userAgent);
        $this->assertEquals(240, $result); // 2^3 * 30 = 240

        // Test with high number of reads (should cap at MAX_LIFETIME)
        $result = $backend->calculateLifetime(200, $userAgent);
        $this->assertEquals(2592000, $result); // MAX_LIFETIME constant
    }

    public function testProcessSessionData(): void
    {
        $backend = new class extends Backend
        {
            public function __construct() {} // Skip parent constructor

            public function processData($sessionData)
            {
                return $this->_processSessionData($sessionData);
            }
        };

        $sessionData = [
            'namespace1' => [
                '__operations' => [
                    ['type' => 'set', 'key' => 'key1', 'value' => 'value1'],
                    ['type' => 'unset', 'key' => 'key2'],
                ],
            ],
            '_direct' => 'direct_value',
        ];

        $result = $backend->processData($sessionData);

        $this->assertArrayHasKey('$set', $result);
        $this->assertArrayHasKey('$unset', $result);
        $this->assertEquals('value1', $result['$set']['data.namespace1.key1']);
        $this->assertEquals('direct_value', $result['$set']['data._direct']);
        $this->assertEquals(1, $result['$unset']['data.namespace1.key2']);
    }

    public function testProcessSessionDataWithEmptyOperations(): void
    {
        $backend = new class extends Backend
        {
            public function __construct() {} // Skip parent constructor

            public function processData($sessionData)
            {
                return $this->_processSessionData($sessionData);
            }
        };

        $sessionData = [
            'namespace1' => [
                'some_data' => 'value',
            ],
            '_direct' => 'direct_value',
        ];

        $result = $backend->processData($sessionData);

        $this->assertArrayHasKey('$set', $result);
        $this->assertEquals('direct_value', $result['$set']['data._direct']);
        $this->assertArrayNotHasKey('$unset', $result);
    }

    public function testProcessSessionDataWithMultipleNamespaces(): void
    {
        $backend = new class extends Backend
        {
            public function __construct() {} // Skip parent constructor

            public function processData($sessionData)
            {
                return $this->_processSessionData($sessionData);
            }
        };

        $sessionData = [
            'namespace1' => [
                '__operations' => [
                    ['type' => 'set', 'key' => 'key1', 'value' => 'value1'],
                ],
            ],
            'namespace2' => [
                '__operations' => [
                    ['type' => 'set', 'key' => 'key2', 'value' => 'value2'],
                    ['type' => 'unset', 'key' => 'key3'],
                ],
            ],
        ];

        $result = $backend->processData($sessionData);

        $this->assertArrayHasKey('$set', $result);
        $this->assertArrayHasKey('$unset', $result);
        $this->assertEquals('value1', $result['$set']['data.namespace1.key1']);
        $this->assertEquals('value2', $result['$set']['data.namespace2.key2']);
        $this->assertEquals(1, $result['$unset']['data.namespace2.key3']);
    }
}
