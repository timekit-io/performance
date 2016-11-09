<?php


use Timekit\Performance\Http\Middleware\PerformanceTracking;
use Timekit\Performance\QueryContainer;

class MiddlewareTest extends PHPUnit_Framework_TestCase
{

    /** 
    * @test
    * @group new
    *  
    */
    public function the_query_is_saved_at_termination()
    {
        // Given
        $mock = Mockery::mock(QueryContainer::class);
        $mock->shouldReceive('save');

        $middleware = new PerformanceTracking($mock);

        // When
        $middleware->terminate(null, null);

        // Then
        $mock->shouldHaveReceived('save')->once();
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group new
     *
     */
    public function the_handle_method_does_not_break_anything()
    {
        // Given
        $mock = Mockery::mock(QueryContainer::class);
        $middleware = new PerformanceTracking($mock);

        // When
        $output = $middleware->handle(null, function($request){
            return true;
        });

        // Then
        $this->assertTrue($output);
    }
}