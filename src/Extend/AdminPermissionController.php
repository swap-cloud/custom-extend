<?php

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Events\ExtensionChanged;

/**
 *
 */
class AdminPermissionController extends \Slowlyo\OwlAdmin\Controllers\AdminPermissionController
{
    /**
     * 覆写自动生成权限节点逻辑
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     */
    public function autoGenerate()
    {
        $menus       = Admin::adminMenuModel()::query()->get()->toArray();
        $slugMap     = Admin::adminPermissionModel()::query()->get(['id', 'slug'])->keyBy('id')->toArray();
        $slugCache   = [];
        $permissions = [];
        foreach ($menus as $menu) {
            $_httpPath =
                $menu['url_type'] == Admin::adminMenuModel()::TYPE_ROUTE ? $this->getHttpPath($menu['url']) : '';

            $menuTitle = $menu['title'];

            // 避免名称重复
            if (in_array($menuTitle, data_get($permissions, '*.name', []))) {
                $menuTitle = sprintf('%s(%s)', $menuTitle, $menu['id']);
            }

            if ($_httpPath) {
                $slug = Str::of(explode('?', $_httpPath)[0])->trim('/')->replace('/', '.')->replace('*', '')->value();
            } else {
                $slug = Str::uuid();
            }

            if (in_array($slug, $slugCache)) {
                $slug = $slug . '.' . $menu['id'];
            }
            $slugCache[] = $slug;

            $permissions[] = [
                'id'           => $menu['id'],
                'name'         => $menuTitle,
                'slug'         => data_get($slugMap, $menu['id'] . '.slug') ?: $slug,
                'http_path'    => json_encode($_httpPath ? [$_httpPath] : ''),
                'custom_order' => $menu['custom_order'],
                'parent_id'    => $menu['parent_id'],
                'created_at'   => $menu['created_at'],
                'updated_at'   => $menu['updated_at'],
            ];
        }

        Admin::adminPermissionModel()::query()->truncate();
        Admin::adminPermissionModel()::query()->insert($permissions);

        $permissionClass = Admin::adminPermissionModel();
        $pivotTable      = (new $permissionClass)->menus()->getTable();

        DB::table($pivotTable)->truncate();
        foreach ($permissions as $item) {
            $query = DB::table($pivotTable);
            $query->insert([
                'permission_id' => $item['id'],
                'menu_id'       => $item['id'],
            ]);

            $_id = $item['id'];
            while (isset($item['parent_id'])?$item['parent_id']:0 != 0) {
                $query->clone()->insert([
                    'permission_id' => $_id,
                    'menu_id'       => $item['parent_id'],
                ]);

                $item = Admin::adminMenuModel()::query()->find($item['parent_id']);
            }
        }
        /**
         * 触发生成权限事件
         */
        ExtensionChanged::dispatch('*', 'gen-permission');

        return $this->response()->successMessage(
            admin_trans('admin.successfully_message', ['attribute' => admin_trans('admin.admin_permission.auto_generate')])
        );
    }

    private function getHttpPath($uri)
    {
        $excepts = ['/', '', '-'];
        if (in_array($uri, $excepts)) {
            return '';
        }

        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        return $uri . '*';
    }
}
