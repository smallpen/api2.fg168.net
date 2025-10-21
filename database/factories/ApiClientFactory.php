<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * 定義模型的預設狀態
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'client_type' => ApiClient::TYPE_API_KEY,
            'api_key' => ApiClient::generateApiKey(),
            'secret' => ApiClient::generateSecret(),
            'token_expires_at' => now()->addYear(),
            'is_active' => true,
            'rate_limit' => ApiClient::DEFAULT_RATE_LIMIT,
        ];
    }

    /**
     * 指定客戶端為未啟用狀態
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * 指定客戶端類型為 Bearer Token
     */
    public function bearerToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => ApiClient::TYPE_BEARER_TOKEN,
        ]);
    }

    /**
     * 指定客戶端類型為 OAuth
     */
    public function oauth(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => ApiClient::TYPE_OAUTH,
        ]);
    }
}
