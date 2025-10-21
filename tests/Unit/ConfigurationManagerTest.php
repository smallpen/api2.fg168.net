<?php

namespace Tests\Unit;

use App\Services\Configuration\ConfigurationManager;
use App\Services\Configuration\ConfigurationCache;
use App\Repositories\FunctionRepository;
use App\Models\ApiFunction;
use App\Exceptions\FunctionNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Mockery;

/**
 * Configuration Manager 單元測試
 */
class ConfigurationManagerTest extends TestCase
{
    protected ConfigurationManager $configManager;
    protected $functionRepository;
    protected $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立 Mock 物件
        $this->functionRepository = Mockery::mock(FunctionRepository::class);
        $this->cache = Mockery::mock(ConfigurationCache::class);
        
        $this->configManager = new ConfigurationManager(
            $this->functionRepository,
            $this->cache
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試從快取載入配置
     */
    public function test_load_configuration_from_cache(): void
    {
        $identifier = 'test.function';
        $function = $this->createMockFunction($identifier);

        // 設定快取返回 Function
        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn($function);

        $result = $this->configManager->loadConfiguration($identifier);

        $this->assertInstanceOf(ApiFunction::class, $result);
        $this->assertEquals($identifier, $result->identifier);
    }

    /**
     * 測試從資料庫載入配置
     */
    public function test_load_configuration_from_database(): void
    {
        $identifier = 'test.function';
        $function = $this->createMockFunction($identifier);

        // 快取中沒有
        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        // 從資料庫載入
        $this->functionRepository->shouldReceive('findActiveByIdentifier')
            ->with($identifier)
            ->once()
            ->andReturn($function);

        // 儲存到快取
        $this->cache->shouldReceive('put')
            ->with($identifier, $function)
            ->once()
            ->andReturn(true);

        $result = $this->configManager->loadConfiguration($identifier);

        $this->assertInstanceOf(ApiFunction::class, $result);
        $this->assertEquals($identifier, $result->identifier);
    }

    /**
     * 測試載入不存在的配置
     */
    public function test_load_nonexistent_configuration_throws_exception(): void
    {
        $identifier = 'nonexistent.function';

        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        $this->functionRepository->shouldReceive('findActiveByIdentifier')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        $this->expectException(FunctionNotFoundException::class);
        $this->configManager->loadConfiguration($identifier);
    }

    /**
     * 測試配置驗證 - 成功案例
     */
    public function test_validate_configuration_success(): void
    {
        $function = $this->createMockFunction('test.function');

        // 不應該拋出例外
        $this->configManager->validateConfiguration($function);
        
        $this->assertTrue(true); // 如果沒有拋出例外，測試通過
    }

    /**
     * 測試配置驗證 - 缺少必要欄位
     */
    public function test_validate_configuration_missing_required_fields(): void
    {
        $function = new ApiFunction([
            'name' => '',
            'identifier' => 'test.function',
            'stored_procedure' => '',
            'is_active' => true,
        ]);
        
        $function->setRelation('parameters', collect());
        $function->setRelation('responses', collect());
        $function->setRelation('errorMappings', collect());

        $this->expectException(ValidationException::class);
        $this->configManager->validateConfiguration($function);
    }

    /**
     * 測試重新載入配置
     */
    public function test_reload_configuration(): void
    {
        $identifier = 'test.function';
        $function = $this->createMockFunction($identifier);

        // 清除快取
        $this->cache->shouldReceive('forget')
            ->with($identifier)
            ->once()
            ->andReturn(true);

        // 從資料庫載入
        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        $this->functionRepository->shouldReceive('findByIdentifier')
            ->with($identifier)
            ->once()
            ->andReturn($function);

        $this->cache->shouldReceive('put')
            ->with($identifier, $function)
            ->once()
            ->andReturn(true);

        $result = $this->configManager->reloadConfiguration($identifier);

        $this->assertInstanceOf(ApiFunction::class, $result);
    }

    /**
     * 測試檢查配置是否存在
     */
    public function test_configuration_exists(): void
    {
        $identifier = 'test.function';
        $function = $this->createMockFunction($identifier);

        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn($function);

        $this->assertTrue($this->configManager->configurationExists($identifier));
    }

    /**
     * 測試檢查不存在的配置
     */
    public function test_configuration_not_exists(): void
    {
        $identifier = 'nonexistent.function';

        $this->cache->shouldReceive('get')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        $this->functionRepository->shouldReceive('findByIdentifier')
            ->with($identifier)
            ->once()
            ->andReturn(null);

        $this->assertFalse($this->configManager->configurationExists($identifier));
    }

    /**
     * 建立 Mock Function 物件
     */
    protected function createMockFunction(string $identifier): ApiFunction
    {
        $function = new ApiFunction([
            'id' => 1,
            'name' => 'Test Function',
            'identifier' => $identifier,
            'description' => 'Test Description',
            'stored_procedure' => 'sp_test',
            'is_active' => true,
        ]);

        // 設定空的關聯
        $function->setRelation('parameters', collect());
        $function->setRelation('responses', collect());
        $function->setRelation('errorMappings', collect());

        return $function;
    }
}
