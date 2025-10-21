<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Configuration\ConfigurationManager;
use App\Services\Configuration\ConfigurationCache;
use App\Repositories\FunctionRepository;

/**
 * Configuration Service Provider
 * 
 * 註冊配置管理相關服務
 */
class ConfigurationServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 註冊 ConfigurationCache 為單例
        $this->app->singleton(ConfigurationCache::class, function ($app) {
            return new ConfigurationCache();
        });

        // 註冊 ConfigurationManager 為單例
        $this->app->singleton(ConfigurationManager::class, function ($app) {
            return new ConfigurationManager(
                $app->make(FunctionRepository::class),
                $app->make(ConfigurationCache::class)
            );
        });
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        // 註冊 Model 事件監聽器，當配置更新時自動清除快取
        $this->registerModelEventListeners();
    }

    /**
     * 註冊 Model 事件監聽器
     */
    protected function registerModelEventListeners(): void
    {
        // 監聽 ApiFunction 的更新和刪除事件
        \App\Models\ApiFunction::updated(function ($function) {
            $this->clearFunctionCache($function);
        });

        \App\Models\ApiFunction::deleted(function ($function) {
            $this->clearFunctionCache($function);
        });

        // 監聽 FunctionParameter 的變更事件
        \App\Models\FunctionParameter::saved(function ($parameter) {
            $this->clearFunctionCacheByParameter($parameter);
        });

        \App\Models\FunctionParameter::deleted(function ($parameter) {
            $this->clearFunctionCacheByParameter($parameter);
        });

        // 監聽 FunctionResponse 的變更事件
        \App\Models\FunctionResponse::saved(function ($response) {
            $this->clearFunctionCacheByResponse($response);
        });

        \App\Models\FunctionResponse::deleted(function ($response) {
            $this->clearFunctionCacheByResponse($response);
        });

        // 監聽 FunctionErrorMapping 的變更事件
        \App\Models\FunctionErrorMapping::saved(function ($mapping) {
            $this->clearFunctionCacheByErrorMapping($mapping);
        });

        \App\Models\FunctionErrorMapping::deleted(function ($mapping) {
            $this->clearFunctionCacheByErrorMapping($mapping);
        });
    }

    /**
     * 清除 Function 快取
     */
    protected function clearFunctionCache(\App\Models\ApiFunction $function): void
    {
        try {
            $cache = $this->app->make(ConfigurationCache::class);
            $cache->forget($function->identifier);
            
            \Log::info("已清除配置快取: {$function->identifier}");
        } catch (\Exception $e) {
            \Log::error("清除配置快取失敗: {$function->identifier}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 根據 Parameter 清除 Function 快取
     */
    protected function clearFunctionCacheByParameter(\App\Models\FunctionParameter $parameter): void
    {
        try {
            $function = $parameter->function;
            if ($function) {
                $this->clearFunctionCache($function);
            }
        } catch (\Exception $e) {
            \Log::error("清除配置快取失敗（透過 Parameter）", [
                'parameter_id' => $parameter->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 根據 Response 清除 Function 快取
     */
    protected function clearFunctionCacheByResponse(\App\Models\FunctionResponse $response): void
    {
        try {
            $function = $response->function;
            if ($function) {
                $this->clearFunctionCache($function);
            }
        } catch (\Exception $e) {
            \Log::error("清除配置快取失敗（透過 Response）", [
                'response_id' => $response->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 根據 ErrorMapping 清除 Function 快取
     */
    protected function clearFunctionCacheByErrorMapping(\App\Models\FunctionErrorMapping $mapping): void
    {
        try {
            $function = $mapping->function;
            if ($function) {
                $this->clearFunctionCache($function);
            }
        } catch (\Exception $e) {
            \Log::error("清除配置快取失敗（透過 ErrorMapping）", [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
