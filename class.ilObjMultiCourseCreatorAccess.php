<?php

/**
 * Access class for Multi Course Creator
 */
class ilObjMultiCourseCreatorAccess extends ilObjectPluginAccess
{
    /**
     * Checks whether a user may invoke a command or not
     * (this method is called by ilAccessHandler::checkAccess)
     *
     * @param string $cmd command (not permission!)
     * @param string $permission permission
     * @param int $ref_id reference id
     * @param int $obj_id object id
     * @param int $user_id user id (if not provided, current user is taken)
     *
     * @return bool true, if everything is ok
     */
    public function _checkAccess(string $cmd, string $permission, int $ref_id, int $obj_id, ?int $user_id = null): bool
    {
        global $DIC;
        
        if ($user_id === null) {
            $user_id = $DIC->user()->getId();
        }

        switch ($permission) {
            case 'read':
            case 'visible':
                // Always allow read/visible for repository administrators
                if ($DIC->rbac()->review()->isAssigned($user_id, SYSTEM_ROLE_ID)) {
                    return true;
                }
                
                // Check if user has read permission on parent
                return $DIC->access()->checkAccessOfUser($user_id, $permission, '', $ref_id);
                
            case 'write':
                // Only allow write access for users who can create courses
                if (!$DIC->access()->checkAccessOfUser($user_id, 'create', '', $ref_id, 'crs')) {
                    return false;
                }
                
                return $DIC->access()->checkAccessOfUser($user_id, $permission, '', $ref_id);
                
            default:
                return $DIC->access()->checkAccessOfUser($user_id, $permission, '', $ref_id);
        }
    }

    /**
     * Check whether goto script will succeed
     */
    public static function _checkGoto(string $target): bool
    {
        global $DIC;
        
        $t_arr = explode("_", $target);
        if ($t_arr[0] !== "xmcc" || ((int) $t_arr[1]) <= 0) {
            return false;
        }

        $ref_id = (int) $t_arr[1];
        
        return $DIC->access()->checkAccess('read', '', $ref_id) ||
               $DIC->access()->checkAccess('visible', '', $ref_id);
    }
}