# 23 Core permissions

This document is the permissions contract for 23 Core. The plugin uses capabilities for every runtime decision; role names are only used when the plugin creates and synchronises the default roles.

## Permissions matrix

| Area | Administrator | 23 Site Manager |
| --- | --- | --- |
| Pages and posts | Full create, edit, publish, delete and restore access | Create, edit, publish and delete own and others' pages and posts |
| Media | Upload, edit and delete media | Upload, edit and delete media |
| Categories and tags | Full management | Full management for content taxonomies |
| Approved page sections | Insert, reorder, remove and edit | Insert, reorder, remove and edit |
| Content inside approved sections | Edit text, images, alternative text and links | Edit text, images, alternative text and links |
| Locked block structures | Lock and unlock | Use locked structures; cannot lock or unlock |
| Navigation content | Edit through WordPress interfaces that can be safely isolated from structural controls | Granted the dedicated `twentythree_manage_navigation` capability. Navigation data may be edited through supported REST-based or future isolated interfaces, but the structural Site Editor is not exposed |
| Navigation structures | Create and delete navigation entities | Not allowed |
| Templates and template parts | Full access | Not allowed |
| Global styles | Full access | Not allowed |
| Structural Site Editor | Full access | Not allowed |
| Themes | Install, activate, update, delete and edit, subject to WordPress/environment policy | Not allowed |
| Plugins | Install, activate, update, delete and edit, subject to WordPress/environment policy | Not allowed |
| WordPress settings | Full access | Not allowed |
| Users | Full administration | Cannot create, edit, delete or promote users and cannot create administrators |
| Custom code | Subject to WordPress/environment policy | Not allowed; Code Editor and Custom HTML are unavailable |

## Plugin capabilities

| Capability | Administrator | 23 Site Manager | Purpose |
| --- | --- | --- | --- |
| `twentythree_manage_sections` | Yes | Yes | Marks users who may compose content from approved sections |
| `twentythree_manage_navigation` | Yes | Yes | Allows navigation-content operations without granting `edit_theme_options` |
| `twentythree_manage_navigation_structure` | Yes | No | Allows creation and deletion of navigation entities |
| `twentythree_unlock_blocks` | Yes | No | Controls access to the block-locking user interface |
| `twentythree_manage_governance` | Yes | No | Reserved for 23 Core governance administration |

## Important WordPress boundary

WordPress currently maps every primitive capability for the `wp_navigation` post type to `edit_theme_options`. Granting that capability to a Site Manager would also expose structural Site Editor areas, so 23 Core does not grant it. Instead, the plugin maps navigation content operations to dedicated capabilities while keeping template, template-part and global-style endpoints restricted.

The WordPress Site Editor itself is therefore Administrator-only. A Site Manager can use navigation editing only through an interface that is isolated to `wp_navigation` content and honours the dedicated capabilities. 23 Core Phase 1 establishes and tests that capability/API boundary; it does not add a custom navigation interface.

## Structural protection model

23 Theme owns templates, template parts and presentation. Patterns may use WordPress content-only or movement/removal locks to protect their internal layout. For users with `twentythree_manage_sections` but without `twentythree_unlock_blocks`, 23 Core hides block-lock controls and the Code Editor. Existing saved blocks are added to the editor allow-list so curation does not make previously saved content disappear.

Block locking is an editorial guardrail rather than a substitute for server-side authorisation. WordPress capabilities remain the security boundary for templates, global styles, settings, code, themes and plugins.
