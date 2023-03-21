<?php

namespace Luchavez\SimpleFiles\Feature\Http\Controllers;

use Tests\TestCase;

/**
 * Class FileControllerTest
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileControllerTest extends TestCase
{
    /**
     * Example Test
     *
     * @test
     */
    public function example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
