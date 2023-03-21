<?php

namespace Luchavez\SimpleFiles\Feature\Models;

use Tests\TestCase;

/**
 * Class FileTest
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileTest extends TestCase
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
