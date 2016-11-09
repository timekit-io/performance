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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
    public function can_count_misc_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // When
        $container->add("DROP database;", 10);

        // Then
        $this->assertEquals(0, $container->getSelectCount());
        $this->assertEquals(0, $container->getInsertCount());
        $this->assertEquals(0, $container->getUpdateCount());
        $this->assertEquals(1, $container->getMiscCount());
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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

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
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // When
        $container->add('SELECT * FROM users', 10);
        $container->add('SELECT * FROM users', 10001);
        $container->add('SELECT * FROM users', 1000);
        $container->add('SELECT * FROM users', 10000);
        $container->add('SELECT * FROM users', 100);

        // Then
        $this->assertEquals(10 + 10001 + 1000 + 10000 + 100, $container->getTotalSQLTime());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_reset_count()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // When
        $container->add('SELECT * FROM users', 10);
        $this->assertEquals(1, $container->allCount());
        $container->resetCounters();

        // Then
        $this->assertEquals(0, $container->allCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_get_slow_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // When
        $container->add('SELECT * FROM users where id = 1', 11);
        $container->add('SELECT * FROM users where id = 2', 1);
        $container->add('SELECT * FROM users where id = 3', 2);
        $container->add('SELECT * FROM users where id = 4', 3);

        // Then
        $queries = $container->allSlowQueries();
        $this->assertEquals(4, $container->allCount());
        $this->assertEquals('[11 ms]: SELECT * FROM users where id = 1', $queries[0]);
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_count_slow_queries()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // When
        $container->add('SELECT * FROM users where id = 1', 14);
        $container->add('SELECT * FROM users where id = 2', 12);
        $container->add('SELECT * FROM users where id = 3', 2);
        $container->add('SELECT * FROM users where id = 4', 13);

        // Then
        $this->assertEquals(3, $container->slowQueryCount());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_save_query_container_to_file_system()
    {
        $this->markTestIncomplete('Not sure on how to test the filesystem');
        $request = $this->createRequest('v2/test', 'get');

        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $container = new QueryContainer($request, $filesystem, 'tmp/');

        // When
        $container->add('SELECT * FROM users where id = 1', 14);

        // Then
        $this->assertTrue($container->save());
    }

    /**
     * @test
     * @group new
     *
     */
    public function can_get_file_name()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');

        // When
        $container = new QueryContainer($request, $this->flysystem, 'tmp/');

        // Then
        $this->assertTrue(is_string($container->getFileName()));
    }

    /**
     * @test
     * @group new
     * @expectedException RuntimeException
     * @expectedExceptionMessage Storage path must end with a /
     */
    public function storage_path_must_end_with_backslash()
    {
        // Given
        $request = $this->createRequest('v2/test', 'get');

        // When
        new QueryContainer($request, $this->flysystem, 'tmp');

        // Then
        // An exception is throw
    }
}