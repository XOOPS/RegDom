<?php
namespace Geekwright\RegDom;

use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase
{
    /**
     * @var PublicSuffixList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new PublicSuffixList();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testContracts()
    {
        $this->assertInstanceOf('\Geekwright\RegDom\PublicSuffixList', $this->object);
    }

    public function testGetSet()
    {
        $tree = $this->object->getTree();
        $this->assertTrue(is_array($tree));
        $this->assertArrayHasKey('com', $tree);
    }

    public function testClearDataDirectory()
    {
        $this->object->clearDataDirectory();
        $tree = $this->object->getTree();
        $this->assertTrue(is_array($tree));
        $this->assertArrayHasKey('com', $tree);
    }

    public function testClearDataDirectoryCacheOnly()
    {
        $this->object->clearDataDirectory(true);
        $tree = $this->object->getTree();
        $this->assertTrue(is_array($tree));
        $this->assertArrayHasKey('com', $tree);
    }
}
