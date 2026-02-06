<?php
/**
 * Render Access Control
 *
 * Access control helper for the Zeus Render Array system.
 * Supports boolean, permission string, callback, and complex access control.
 *
 * @author Evangelos Rokas
 * @version 1.0
 * @date February 2026
 */

class RenderAccess {

    /**
     * Check if element has access
     *
     * @param array $element Render array element
     * @param array $context Additional context
     * @return bool True if access is granted
     */
    public static function check(array $element, array $context = []): bool {
        // No #access property means access is granted by default
        if (!isset($element['#access'])) {
            return true;
        }

        $access = $element['#access'];

        // Boolean access
        if (is_bool($access)) {
            return $access;
        }

        // Permission string access
        if (is_string($access)) {
            return self::checkPermission($access);
        }

        // Callback access
        if (is_callable($access)) {
            return (bool)call_user_func($access, $element, $context);
        }

        // Array access (multiple conditions)
        if (is_array($access)) {
            return self::checkArrayAccess($access, $element, $context);
        }

        // Default: deny access for unknown types
        return false;
    }

    /**
     * Check permission using SecurityClass
     *
     * @param string $permission Permission name
     * @return bool True if user has permission
     */
    private static function checkPermission(string $permission): bool {
        // Check if SecurityClass exists and has the hasPermission method
        if (class_exists('SecurityClass')) {
            if (method_exists('SecurityClass', 'hasPermission')) {
                return SecurityClass::hasPermission($permission);
            }

            // Fallback: try checkPermission method
            if (method_exists('SecurityClass', 'checkPermission')) {
                return SecurityClass::checkPermission($permission);
            }

            // Fallback: check if user is logged in for any permission
            if (method_exists('SecurityClass', 'userLoggedIn')) {
                return SecurityClass::userLoggedIn();
            }
        }

        // If SecurityClass doesn't exist, grant access by default
        return true;
    }

    /**
     * Check array-based access conditions
     *
     * @param array $access Access configuration array
     * @param array $element Render array element
     * @param array $context Additional context
     * @return bool True if all conditions pass
     */
    private static function checkArrayAccess(array $access, array $element, array $context): bool {
        $allConditions = true;

        // Check permission if specified
        if (isset($access['permission'])) {
            $allConditions = $allConditions && self::checkPermission($access['permission']);
        }

        // Check callback if specified
        if (isset($access['callback']) && is_callable($access['callback'])) {
            $allConditions = $allConditions && (bool)call_user_func($access['callback'], $element, $context);
        }

        // Check boolean value if specified
        if (isset($access['value'])) {
            $allConditions = $allConditions && (bool)$access['value'];
        }

        // Check operator: 'AND' (default) or 'OR'
        $operator = strtoupper($access['operator'] ?? 'AND');

        if ($operator === 'OR') {
            // For OR, we need at least one condition to be true
            // This is a simplified implementation - rebuild with OR logic
            $anyCondition = false;

            if (isset($access['permission'])) {
                $anyCondition = $anyCondition || self::checkPermission($access['permission']);
            }
            if (isset($access['callback']) && is_callable($access['callback'])) {
                $anyCondition = $anyCondition || (bool)call_user_func($access['callback'], $element, $context);
            }
            if (isset($access['value'])) {
                $anyCondition = $anyCondition || (bool)$access['value'];
            }

            return $anyCondition;
        }

        // Default AND logic
        return $allConditions;
    }
}
