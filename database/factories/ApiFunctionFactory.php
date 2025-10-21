<?php

namespace Database\Factories;

use App\Models\ApiFunction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiFunction>
 */
class ApiFunctionFactory extends Factory
{
    protected $model = ApiFunction::class;

    /**
     * 定義模型的預設狀態
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'name' => $name,
            'identifier' => Str::slug($name, '.'),
            'description' => fake()->sentence(),
            'stored_procedure' => 'sp_' . Str::slug($name, '_'),
            'is_active' => true,
            'created_by' => 1,
        ];
    }

    /**
     * 指定 Function 為未啟用狀態
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
