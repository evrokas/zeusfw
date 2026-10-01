<?php

/*
 * Step 1 of a planned live-editing feature: a small, transparent, opt-in
 * overlay showing which .zetem template a page's main content actually
 * came from -- nothing editable yet, just the source-of-truth indicator
 * later steps (click-to-edit, write-back to disk) will build on.
 *
 * How "which template" is actually known: Renderer::render()
 * (core/templates/ZETEMTemplate.php) is the one choke point every .zetem
 * render goes through, and now keeps a depth-aware log of every OUTERMOST
 * call (a template's own {{ }}/{% %} expressions can nest further render()
 * calls -- e.g. a form field via FormElement::render() -- and those must
 * never count as "a page section"). RouterClass::routerCallFunction()
 * (core/router/Router.php) brackets the matched route's own handler
 * invocation with a mark/snapshot into Renderer::$lastContentRenders, so
 * this module sees exactly what rendered during dispatch -- never a
 * chrome template like header.zetem/page.zetem, which render afterwards,
 * inside Kernel::renderPage(). A handler that builds smaller pieces first
 * (field/widget sub-templates) and composes the real page template last
 * (the pattern every handler in this codebase's maker-generated/hand-
 * written forms already follows) means the LAST entry in that list is the
 * real content template -- see resolveContentSource() below.
 *
 * Gated two ways, since a resolved server-side file path is real
 * information disclosure even to a logged-in staff account:
 *   1. live_edit.yaml's own `live_edit_enabled` (default false) / the
 *      'enabled' param an app's own template call site passes explicitly
 *      to module('live_edit', [...]) -- an app has to turn this on on
 *      purpose, not get it for free by merely listing the module.
 *   2. rbacClass::isPermitted(ZEUSFW_PERM_LIVE_EDIT) -- 'content-live-edit'
 *      (core/lib/Rbac.php), a dedicated, narrow permission (not
 *      ZEUSFW_PERM_MANAGE_USERS -- seeing a template path has nothing to
 *      do with user/role administration). An is_superuser role never
 *      needs this granted explicitly, same as every other permission slug
 *      in this framework -- rbacClass::isPermitted()'s own bypass already
 *      covers it. zeusfw_app_live_edit_permission() is the usual
 *      function_exists() override point (same convention as
 *      zeusfw_app_backup_permission()) for an app wanting a different
 *      permission slug instead.
 *
 * Failure on either gate renders nothing ('') -- this is a decorative
 * overlay bolted onto an otherwise normal page, not a protected route, so
 * unlike rbacClass::require() it must never replace the page with a
 * rendered 401.
 */

if (!function_exists('zeusfw_app_live_edit_permission')) {
    function zeusfw_app_live_edit_permission(): string {
        return ZEUSFW_PERM_LIVE_EDIT;
    }
}

class liveEditModule extends moduleClass {
    // moduleClass's own $moduledir is private, not inherited-accessible -
    // needed here to resolve this module's own CSS/JS URLs at render()
    // time, same reason accessibilityModule keeps its own copy.
    private $adir;

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->adir = $adir;

        $rt = yaml_parse_file(__DIR__ . '/live_edit.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);
    }

    /*
     * $params:
     *   'enabled' => bool, explicit per-call-site override of
     *                live_edit.yaml's own live_edit_enabled default.
     */
    function run($params = array()) {
        global $kernel;

        $enabled = array_key_exists('enabled', $params)
            ? (bool)$params['enabled']
            : (bool)$kernel->getConfig('live_edit_enabled');
        if (!$enabled) {
            return '';
        }

        // Cheapest check first -- an anonymous visitor never reaches the
        // RBAC query below at all.
        if (!$kernel->getUserName()) {
            return '';
        }

        if (!rbacClass::isPermitted(zeusfw_app_live_edit_permission())) {
            return '';
        }

        return $this->render($params);
    }

    function render($params = array()) {
        global $kernel;

        $entry = $this->resolveContentSource();
        if ($entry === null) {
            return '';
        }

        $cssUrl = rel_url($kernel->resolveModuleDir('@core/css/live_edit.css', $this->adir, $this->getName()));
        $jsUrl = rel_url($kernel->resolveModuleDir('@core/js/live_edit.js', $this->adir, $this->getName()));

        return $this->renderTemplate([
            'templateName' => $entry['file'],
            'templatePath' => $entry['relpath'],
            'copiedLabel' => 'Copied',
            'cssUrl' => $cssUrl,
            'jsUrl' => $jsUrl,
        ]);
    }

    /*
     * The resolved content-source entry for this request, or null if
     * nothing rendered during dispatch (a redirect, a 401/404 -- there's
     * nothing meaningful to show in either case).
     *
     * @return array{file:string,relpath:string}|null
     */
    private function resolveContentSource(): ?array {
        $renders = Renderer::$lastContentRenders;
        if (!$renders) {
            return null;
        }

        $last = end($renders);
        if (!$last['path']) {
            return null;
        }

        return ['file' => $last['file'], 'relpath' => $this->relativizePath($last['path'])];
    }

    // Strips the app-root common prefix off an absolute template path --
    // the exact technique core/kernel/utils.php's echopre() already uses
    // for debug-trace paths, reused here so this overlay never displays
    // an absolute server filesystem path to whoever's looking at it.
    //
    // Deliberately NOT realpath()'d first: this app's web/core is a
    // symlink into the zeusfw checkout, and realpath() would resolve a
    // core template's path to the real, out-of-app-tree zeusfw location --
    // sharing no common prefix with __APPDIR__ at all, defeating
    // relativization for exactly the templates (error pages, core-only
    // module templates) that most need it. The stray "./"/"//" this
    // leaves behind (the configured template search path itself isn't
    // always fully normalized) is cleaned up with plain string ops
    // instead, which don't touch symlinks.
    private function relativizePath(string $absolute): string {
        if (!defined('__APPDIR__')) {
            return $absolute;
        }

        $fs = explode('/', $absolute);
        $ap = explode('/', __APPDIR__);
        while (isset($fs[0]) && isset($ap[0]) && ($fs[0] === $ap[0])) {
            array_shift($fs);
            array_shift($ap);
        }
        $rel = implode('/', $fs);
        $rel = preg_replace('#/\./#', '/', $rel);
        return ltrim($rel, './');
    }
}

function register_live_edit_module() {
    global $kernel;
    $kernel->registerModule(new liveEditModule(__DIR__, 'live_edit', 'live_edit.zetem'));
}
