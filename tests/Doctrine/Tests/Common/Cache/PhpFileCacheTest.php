<?php

namespace Doctrine\Tests\Common\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\PhpFileCache;

/**
 * @group DCOM-101
 */
class PhpFileCacheTest extends BaseFileCacheTest
{
    public function provideDataToCache() : array
    {
        $data = parent::provideDataToCache();

        unset($data['object'], $data['object_recursive']); // PhpFileCache only allows objects that implement __set_state() and fully support var_export()

        if (PHP_VERSION_ID < 70002) {
            unset($data['float_zero']); // var_export exports float(0) as int(0): https://bugs.php.net/bug.php?id=66179
        }

        return $data;
    }

    public function testImplementsSetState() : void
    {
        $cache = $this->_getCacheDriver();

        // Test save
        $cache->save('test_set_state', new SetStateClass([1,2,3]));

        //Test __set_state call
        $this->assertCount(0, SetStateClass::$values);

        // Test fetch
        $value = $cache->fetch('test_set_state');
        $this->assertInstanceOf(SetStateClass::class, $value);
        $this->assertEquals([1,2,3], $value->getValue());

        //Test __set_state call
        $this->assertCount(1, SetStateClass::$values);

        // Test contains
        $this->assertTrue($cache->contains('test_set_state'));
    }

    /**
     * @group 154
     */
    public function testNotImplementsSetState() : void
    {
        $cache = $this->_getCacheDriver();

        $cache->save('test_not_set_state', new NotSetStateClass([5,6,7]));
        $this->assertEquals(new NotSetStateClass([5,6,7]), $cache->fetch('test_not_set_state'));
    }

    /**
     * @group 154
     */
    public function testNotImplementsSetStateInArray() : void
    {
        $cache = $this->_getCacheDriver();

        $cache->save('test_not_set_state_in_array', [new NotSetStateClass([4,3,2])]);
        $this->assertEquals([new NotSetStateClass([4,3,2])], $cache->fetch('test_not_set_state_in_array'));
        $this->assertTrue($cache->contains('test_not_set_state_in_array'));
    }

    public function testGetStats() : void
    {
        $cache = $this->_getCacheDriver();
        $stats = $cache->getStats();

        $this->assertNull($stats[Cache::STATS_HITS]);
        $this->assertNull($stats[Cache::STATS_MISSES]);
        $this->assertNull($stats[Cache::STATS_UPTIME]);
        $this->assertEquals(0, $stats[Cache::STATS_MEMORY_USAGE]);
        $this->assertGreaterThan(0, $stats[Cache::STATS_MEMORY_AVAILABLE]);
    }

    protected function _getCacheDriver() : CacheProvider
    {
        return new PhpFileCache($this->directory);
    }
}

class NotSetStateClass
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}

class SetStateClass extends NotSetStateClass
{
    public static $values = [];

    public static function __set_state($data)
    {
        self::$values = $data;
        return new self($data['value']);
    }
}
