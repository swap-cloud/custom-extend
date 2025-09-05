<?php
if (!function_exists('admin_user_role_check')) {
    /**
     * 检查当前管理员用户 是否属于指定的角色
     * @param string|array $role
     * @return bool
     */
    function admin_user_role_check(string|array $role)
    {
        $adminUserRoles = admin_user()->roles->pluck('slug');
        $adminUserRoles = $adminUserRoles ? $adminUserRoles->toArray() : [];

        if (is_array($role) && count($role) >= 1) {
            $success = true;
            foreach ($role as $item) {
                if (!in_array($item, $adminUserRoles)) {
                    $success = false;
                    break;
                }
            }
            return $success;
        }
        if (in_array($role, $adminUserRoles)) {
            return true;
        }
        return false;
    }
}
