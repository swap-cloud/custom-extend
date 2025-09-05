<?php

namespace SwapCloud\CustomExtend\Extend;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Models\AdminRole;
use Slowlyo\OwlAdmin\Models\AdminUser;

/**
 *
 */
class Menu extends \Slowlyo\OwlAdmin\Support\Cores\Menu
{
    /**
     * 额外菜单
     *
     * @return array|array[]
     */
    public function extra()
    {
        $extraMenus = [];

        if (Admin::config('admin.auth.enable')) {
            $extraMenus[] = [
                'name'      => 'user_setting',
                'path'      => '/user_setting',
                'component' => 'amis',
                'meta'      => [
                    'hide'         => true,
                    'title'        => admin_trans('admin.user_setting'),
                    'icon'         => 'material-symbols:manage-accounts',
                    'singleLayout' => 'basic',
                ],
            ];
        }

        if (Admin::config('admin.show_development_tools')) {
            if(in_array('Administrator',admin_user()->roles->pluck('name')?admin_user()->roles->pluck('name')->toArray():[])){
                $extraMenus = array_merge($extraMenus, $this->devToolMenus());
            }
        }

        return $extraMenus;
    }
}
