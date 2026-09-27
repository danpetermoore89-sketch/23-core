# 23 Core

23 Core is the shared governance plugin for WordPress websites maintained by 23 Web. Phase 1 provides a capability-based Site Manager role, editor curation and a safe permissions boundary between day-to-day content work and structural administration.

Version 0.1.0 supports WordPress 6.6 or later and PHP 7.4 or later. It is designed to complement 23 Theme, but contains no theme presentation and does not require that theme to run.

## Architecture

- `twentythree-core.php` is the plugin bootstrap and lifecycle entry point.
- `src/class-role-manager.php` creates and synchronises roles and capabilities.
- `src/class-editor-governance.php` curates the post/page block editor for governed users.
- `src/class-navigation-access.php` separates navigation-content permissions from broad theme administration.
- `docs/permissions.md` is the human-readable permissions contract.

Runtime decisions use capabilities, not role-name comparisons. The role slug exists only to provide a useful default permission set.

## Permissions

The `23 Site Manager` role can manage pages, posts, media, categories and tags. It can compose pages from approved sections and edit text, images, alternative text and links within the limits applied by WordPress block locking.

The role cannot administer themes, plugins, settings, users, templates, template parts or global styles. It cannot open block lock controls or the Code Editor and is not granted `unfiltered_html`.

See [docs/permissions.md](docs/permissions.md) for the complete matrix and the dedicated capability names.

### Navigation boundary

WordPress maps block-navigation permissions to `edit_theme_options`, which also opens structural Site Editor features. 23 Core replaces the `wp_navigation` post type's primitive capabilities with dedicated navigation capabilities. Site Managers may update existing navigation content through a supported REST-based or future isolated interface, but cannot create/delete navigation entities and are not admitted to the structural Site Editor.

Phase 1 intentionally does not add a bespoke navigation screen. This avoids presenting a brittle imitation of the core navigation editor while still establishing a testable least-privilege API boundary.

## Editor governance

Site Managers receive an allow-list of ordinary content and layout blocks needed by approved theme patterns. Blocks already present in a post or page are added to that list so activating the plugin does not remove saved content. Remote-directory patterns are disabled for governed editors; local theme and registered patterns remain available.

Block locking is an editorial safeguard. A Site Manager cannot use WordPress lock controls or the Code Editor, while a user with `twentythree_unlock_blocks` can use the normal WordPress controls. Server-side capabilities remain the security boundary for privileged resources.

## Activation and upgrades

Activate 23 Core from Plugins or with WP-CLI:

```sh
wp plugin activate twentythree-core
```

Activation creates or updates the `23 Site Manager` role and grants 23 Core's administrative capabilities to the Administrator role. On a version change, the same synchronisation runs automatically. Only capabilities previously recorded as plugin-managed are removed during an upgrade, so capabilities added by another integration are preserved.

For a multisite network activation, existing sites are synchronised. Phase 1 does not automatically provision the role on sites created after network activation; activate or synchronise the plugin for each new site.

## Deactivation, uninstall and recovery

Ordinary deactivation deliberately leaves the role, user assignments, capabilities and version options in place. This prevents users from losing their assigned role or content access during maintenance.

There is no automatic uninstall cleanup in Phase 1. Removing a live role can strand user assignments, so destructive cleanup must be an explicit, supervised migration in a future release.

Recovery access always uses a normal WordPress Administrator account. Administrators retain WordPress core capabilities independently of 23 Core. If custom capabilities need to be repaired after reactivation, an administrator can resynchronise them with WP-CLI:

```sh
wp eval '\TwentyThree\Core\Role_Manager::synchronize();'
```

## Scope

23 Core Phase 1 contains no branding controls, updater, directory, membership, schema, licensing, analytics or custom-block functionality.

## License

23 Core is licensed under GPL-2.0-or-later.
