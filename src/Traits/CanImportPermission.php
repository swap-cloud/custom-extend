<?php

namespace SwapCloud\CustomExtend\Traits;

use Illuminate\Support\Arr;
use Slowlyo\OwlAdmin\Admin;
use Illuminate\Support\Facades\Validator;

/**
 * @property \Symfony\Component\Console\Output\OutputInterface $output
 */
trait CanImportPermission
{

    /**
     * 获取权限列表.
     *
     * @return array
     */
    protected function permission()
    {
        return $this->permission;
    }

    /**
     * 添加权限.
     *
     * @param array $permission
     *
     * @throws \Exception
     */
    protected function addPermission(array $permission = [])
    {
        $permission = $permission ?: $this->permission();

        if (!Arr::isAssoc($permission)) {
            foreach ($permission as $v) {
                $this->addPermission($v);
            }

            return;
        }

        if (!$this->validatePermission($permission)) {
            return;
        }

        if ($permissionModel = $this->getPermissionModel()) {
            $lastOrder = $permissionModel::max('custom_order');

            $permissionModel::query()->insert([
                'parent_id' => intval($this->getParentPermissionId($permission['parent'] ?? 0)),
                'custom_order' => $lastOrder + 1,
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'http_method' => json_encode($permission['method']),
                'http_path' => json_encode($permission['path']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'extension' => $this->getName(),
            ]);
        }
    }
    protected function validatePermission($permission)
    {
        if(!(isset($permission['name']) && strlen($permission['name'])>=1)){
            return false;
        }
        if(!(isset($permission['slug']) && strlen($permission['slug'])>=1)){
            return false;
        }
        return true;
    }

    /**
     * 刷新权限.
     *
     * @throws \Exception
     */
    protected function refreshPermission()
    {
        $this->flushPermission();

        $this->addPermission();
    }

    /**
     * 根据名称获取权限ID.
     *
     * @param int|string $parent
     *
     * @return int
     */
    protected function getParentPermissionId($parent)
    {
        if (is_numeric($parent) || !$parent) {
            return $parent;
        }

        $permissionModel = $this->getPermissionModel();

        return $permissionModel::query()
            ->where('slug', $parent)
            ->where('extension', $this->getName())
            ->value('id') ?: 0;
    }

    /**
     * 删除权限.
     */
    protected function flushPermission()
    {
        $permissionModel = $this->getPermissionModel();

        if (!$permissionModel) {
            return;
        }

        $permissionModel::query()
            ->where('extension', $this->getName())
            ->delete();
    }

    protected function getPermissionModel()
    {
        return Admin::adminPermissionModel();
    }
}
