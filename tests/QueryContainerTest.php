<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Timekit\Performance\QueryContainer;

class QueryContainerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Filesystem
     */
    private $flysystem;

    public function setUp()
    {
        parent::setUp();
        $this->flysystem = Mockery::mock(Filesystem::class);
    }

    private function createRequest(string $url, string $method)
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getPathInfo')->andReturn($url);
        $request->shouldReceive('getMethod')->andReturn($method);

        return $request;
    }

    /**
    * @test
    * @group new
    *
    */
    public function can_count_all_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add('SELECT * FROM users', 10);

        // Then
        $this->assertEquals(1, $container->allCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_count_select_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add('SELECT * FROM users', 10);

        // Then
        $this->assertEquals(1, $container->getSelectCount());
        $this->assertEquals(0, $container->getInsertCount());
        $this->assertEquals(0, $container->getUpdateCount());
        $this->assertEquals(0, $container->getDeleteCount());
        $this->assertEquals(1, $container->allCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_count_insert_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add("INSERT INTO users (name, email) VALUES ('vk', 'vk@timekit.io')", 10);

        // Then
        $this->assertEquals(0, $container->getSelectCount());
        $this->assertEquals(1, $container->getInsertCount());
        $this->assertEquals(0, $container->getUpdateCount());
        $this->assertEquals(0, $container->getDeleteCount());
        $this->assertEquals(1, $container->allCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_count_update_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add("UPDATE users SET name = 'visti'", 10);

        // Then
        $this->assertEquals(0, $container->getSelectCount());
        $this->assertEquals(0, $container->getInsertCount());
        $this->assertEquals(1, $container->getUpdateCount());
        $this->assertEquals(0, $container->getDeleteCount());
        $this->assertEquals(1, $container->allCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_count_delete_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add("DELETE from users WHERE id = 1", 10);

        // Then
        $this->assertEquals(0, $container->getSelectCount());
        $this->assertEquals(0, $container->getInsertCount());
        $this->assertEquals(0, $container->getUpdateCount());
        $this->assertEquals(1, $container->getDeleteCount());
        $this->assertEquals(1, $container->allCount());
    }
}