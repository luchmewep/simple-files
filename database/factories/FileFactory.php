<?php

namespace Database\Factories;

// Model
use Luchavez\SimpleFiles\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

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
