<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
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
     * @param $sql
     * @param $bindings
     * @param $time
     * @return QueryExecuted
     */
    private function createQueryExecutedEvent(string $sql, array $bindings, float $time): QueryExecuted
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getName')->andReturn('mysql');
        $queryExecuted = new QueryExecuted($sql, $bindings, $time, $connection);

        return $queryExecuted;
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

    /**
     * @test
     * @group new
     *
     */
    public function can_get_url()
    {
        // Given
        $request = $this->createRequest('v2/test/random/url', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $url = $container->getUrl();

        // Then
        $this->assertEquals('v2/test/random/url', $url);
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_get_slow_query_threshold()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $threshold = $container->getSlowQueryThreshold();

        // Then
        $this->assertEquals(10, $threshold);
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_add_event_by_query_executed_event()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        $queryExecuted = $this->createQueryExecutedEvent("select * from users where id = ?", ['1'], 0.75);

        // When
        $container->addByEvent($queryExecuted);

        // Then
        $this->assertEquals(1, $container->allCount());
        $this->assertEquals(1, $container->getSelectCount());
        $this->assertEquals(0, $container->getInsertCount());
        $this->assertEquals(0, $container->getUpdateCount());
        $this->assertEquals(0, $container->getDeleteCount());
    }

    /**
    * @test
    * @group new
    *
    */
    public function can_get_the_slowest_query_time()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add('SELECT * FROM users', 10);
        $container->add('SELECT * FROM users', 10001);
        $container->add('SELECT * FROM users', 1000);
        $container->add('SELECT * FROM users', 10000);
        $container->add('SELECT * FROM users', 100);

        // Then
        $this->assertEquals(10001, $container->getSlowestQuery());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_get_the_total_query_time()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem);

        // When
        $container->add('SELECT * FROM users', 10);
        $container->add('SELECT * FROM users', 10001);
        $container->add('SELECT * FROM users', 1000);
        $container->add('SELECT * FROM users', 10000);
        $container->add('SELECT * FROM users', 100);

        // Then
        $this->assertEquals(10 + 10001 + 1000 + 10000 + 100, $container->getTotalSQLTime());
    }
}