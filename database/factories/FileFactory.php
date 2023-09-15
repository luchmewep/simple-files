<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Luchavez\SimpleFiles\Models\File;

/**
 * Class File
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
