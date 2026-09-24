<?php
/*
 * Generalized ZeusFW module reading a zops (github.com/evrokas/zops)
 * backup status report -- originally built app-specifically inside
 * ZPMS's own web/modules/backup/ (which read bin/backup.sh's
 * web/files/logs/{backup_status,backup_generations}.json), moved into
 * core so any ZeusFW app gets the same status page by adding "backup" to
 * its own settings.info.yaml modules: list -- see this framework's own
 * CLAUDE.md, "RBAC engine + admin CRUD UI moved into core", for the
 * identical precedent this follows (schema/engine into core, app keeps
 * only its own config).
 *
 * Reads from $kernel->getConfig('zops_backup_status_file') -- each app
 * sets this in its own config/site.info.yaml to the exact STATUS_FILE
 * path configured in that site's own /etc/zops/sites.d/<id>.conf (see
 * lib/zops/docs/PROTOCOL.md for the report's shape: status, stage, error,
 * per-element byte/file counts, per-destination tier counts). Degrades to
 * a plain "not configured yet" state when unset or the file doesn't
 * exist -- never a fatal error, matching this framework's own
 * optional-integration conventions (e.g. accessibilityModule's config-
 * driven profiles/options).
 *
 * Gated by ZEUSFW_PERM_MANAGE_USERS by default (the same "can manage
 * users/roles/permissions" permission admin_crud.php already checks) --
 * backup status reveals real infrastructure detail (destination hosts,
 * retention counts, failure messages), so an admin-only surface is the
 * safe default for an app that hasn't opted into anything narrower.
 *
 * zeusfw_app_backup_permission() (below) is an opt-in override, same
 * function_exists() extension-point convention as
 * zeusfw_app_resolve_user_roles()/zeusfw_app_resolve_ernsauth_username()
 * (core/lib/Rbac.php / ErnsAuth.php) -- an app defines this to name its
 * own, narrower permission constant (e.g. ZPMS's own ZPMS_PERM_BACKUP_ACCESS,
 * kept in its rbac.php specifically for this) instead of requiring
 * ZEUSFW_PERM_MANAGE_USERS, so a role that should only ever see backup
 * status doesn't also need to be trusted with user/role administration
 * just to reach this page. Undefined (the default) keeps today's
 * ZEUSFW_PERM_MANAGE_USERS-only behavior exactly as-is.
 */

if (!function_exists('zeusfw_app_backup_permission')) {
    function zeusfw_app_backup_permission(): string {
        return ZEUSFW_PERM_MANAGE_USERS;
    }
}

class backupModule extends moduleClass {

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);

        $rt = yaml_parse_file(__DIR__ . '/backup.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);

        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    function render($params = array()) {
        return $this->renderTemplate([
            'report' => $this->readReport(),
        ]);
    }

    function run($params = array()) {
        if (($ret = rbacClass::require(zeusfw_app_backup_permission()))) return $ret;
        return $this->render($params);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function readReport() {
        global $kernel;
        $path = $kernel->getConfig('zops_backup_status_file');
        if (!$path || !is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return (is_array($data) && isset($data['status'])) ? $data : null;
    }
}

function register_backup_module() {
    global $kernel;
    $kernel->registerModule(new backupModule(__DIR__, 'backup', 'backup.zetem'));
}
