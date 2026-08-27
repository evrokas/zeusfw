<?php

/**
 * Generic enable/disable toggle for optional framework-provided
 * packages/modules (e.g. the admin_user_crud package -- see
 * core/modules/admin/admin_crud.php and core/config/zeusfw.info.yaml's
 * own `routes:`/`disabled_packages:` comments for that one's full
 * design). Any future framework package should register itself the same
 * way: declare its routes in core/config/zeusfw.info.yaml, pick a unique
 * package name string, and call packagesClass::isEnabled('that-name') as
 * the first line of every one of its route handlers.
 *
 * Deliberately NOT a router-level gate (Router.php's dispatch is shared
 * by every route in every app on this framework -- touching it would be
 * a much wider blast radius than this feature needs). Instead each
 * gated handler checks in first, the same way rbacClass::require() is
 * already checked in first for a permission gate -- a disabled package
 * behaves exactly like a route that failed its own internal check
 * (returns error_404()), not like a route that was never registered.
 *
 * `disabled_packages` is deliberately a flat, accumulating LIST rather
 * than a map of booleans (`{package: {enabled: false}}`) -- Kernel::
 * addConfig()'s array_merge_recursive() concatenates two lists safely
 * across the framework/site/app config layers, but corrupts a nested
 * scalar override into an array instead of letting the later layer win
 * (confirmed: array_merge_recursive(['enabled'=>true], ['enabled'=>false])
 * produces ['enabled'=>[true,false]], not ['enabled'=>false]). A list
 * that any layer can only ever ADD to has no such collision -- disabling
 * a package at any one layer (core/config/zeusfw.info.yaml, an app's own
 * config/site.info.yaml, or config/settings.info.yaml) is final; no layer
 * can silently re-enable what a less specific layer already turned off.
 */
class packagesClass {
    static function isEnabled(string $package): bool {
        global $kernel;
        $disabled = $kernel->getConfig('disabled_packages');
        return !in_array($package, $disabled, true);
    }
}
