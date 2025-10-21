<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository Interface
 * 
 * 定義 Repository 的基本操作介面
 */
interface RepositoryInterface
{
    /**
     * 取得所有記錄
     */
    public function all(): Collection;

    /**
     * 根據 ID 查找記錄
     */
    public function find(int $id): ?Model;

    /**
     * 根據 ID 查找記錄，找不到則拋出例外
     */
    public function findOrFail(int $id): Model;

    /**
     * 根據條件查找第一筆記錄
     */
    public function findBy(array $criteria): ?Model;

    /**
     * 根據條件查找所有記錄
     */
    public function findAllBy(array $criteria): Collection;

    /**
     * 建立新記錄
     */
    public function create(array $data): Model;

    /**
     * 更新記錄
     */
    public function update(int $id, array $data): bool;

    /**
     * 刪除記錄
     */
    public function delete(int $id): bool;

    /**
     * 檢查記錄是否存在
     */
    public function exists(array $criteria): bool;

    /**
     * 計算記錄數量
     */
    public function count(array $criteria = []): int;
}
