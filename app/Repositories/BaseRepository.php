<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Repository
 * 
 * 提供 Repository 的基本實作
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * 取得所有記錄
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * 根據 ID 查找記錄
     */
    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * 根據 ID 查找記錄，找不到則拋出例外
     */
    public function findOrFail(int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * 根據條件查找第一筆記錄
     */
    public function findBy(array $criteria): ?Model
    {
        return $this->model->where($criteria)->first();
    }

    /**
     * 根據條件查找所有記錄
     */
    public function findAllBy(array $criteria): Collection
    {
        return $this->model->where($criteria)->get();
    }

    /**
     * 建立新記錄
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * 更新記錄
     */
    public function update(int $id, array $data): bool
    {
        $record = $this->findOrFail($id);
        return $record->update($data);
    }

    /**
     * 刪除記錄
     */
    public function delete(int $id): bool
    {
        $record = $this->findOrFail($id);
        return $record->delete();
    }

    /**
     * 檢查記錄是否存在
     */
    public function exists(array $criteria): bool
    {
        return $this->model->where($criteria)->exists();
    }

    /**
     * 計算記錄數量
     */
    public function count(array $criteria = []): int
    {
        $query = $this->model->newQuery();

        if (!empty($criteria)) {
            $query->where($criteria);
        }

        return $query->count();
    }

    /**
     * 取得 Model 實例
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * 設定 Model 實例
     */
    public function setModel(Model $model): void
    {
        $this->model = $model;
    }
}
