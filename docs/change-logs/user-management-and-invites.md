# User Management and Invites — Change Log

Implements [docs/plans/user-management-and-invites.md](../plans/user-management-and-invites.md).

**Date**: 2026-07-06
**Branch**: `user-invites`

## Summary

Added a role-based user management system to the admin panel: three fixed roles (`viewer` / `editor` / `admin`) on spatie/laravel-permission, policies enforcing them across every resource and settings page, a `UserResource` for CRUD, an email-based invite flow with tokenised registration, an invite-only vs open-registration toggle, self-service password reset, and a `user:set-role` bootstrap command.

## Roles & permissions

- Installed `spatie/laravel-permission` (v8). Published a fresh `create_permission_tables` migration and deleted the orphaned `database/migrations/permissions/2023_04_12_173431_create_permission_tables.php` (dead code that never ran).
- `App\Enums\UserRole` (string-backed `viewer`/`editor`/`admin`, implements `HasLabel`/`HasColor`) is the typed source of role names, so `hasRole()`/`assignRole()`/selects never use magic strings.
- `User` now uses `HasRoles` with helpers `isAdmin()`, `canEdit()` (editor or admin), and `isLastAdmin()`. `canAccessPanel()` still returns `true` for all authenticated users — viewers get a read-only panel; capability is governed by policies.
- `Database\Seeders\Prep\RoleSeeder` (added to the base seed) idempotently `firstOrCreate`s the three roles and resets the permission cache.
- Data migration `assign_admin_role_to_existing_users` promotes every pre-existing user to `admin` (preserving their prior unrestricted access); a no-op on fresh installs.

### Policies (auto-discovered, `app/Policies/`)

- `TrovePolicy`, `CollectionPolicy`, `TagPolicy`, `TagTypePolicy`, `TroveTypePolicy`: everyone may `viewAny`/`view`; mutating abilities require `canEdit()`.
- `UserPolicy`, `InvitePolicy`: admin-only. `UserPolicy::delete()` blocks self-deletion and deleting the last admin.
- `FilamentCommentPolicy`: overrides the package default so only editors/admins can create comments (deletion stays limited to the comment's author). Wired via published `config/filament-comments.php` `model_policy`.
- `SiteOptionsPage`/`SiteContentPage` gained `canAccess() → isAdmin()`, which also hides them from non-admins' navigation.

## Invites

- `invites` table + `App\Models\Invite`: `role` cast to `UserRole`, datetime casts, `inviter()` relation, `scopePending()`, a derived `status` accessor (`App\Enums\InviteStatus`: Pending/Expired/Accepted), `isUsable()`, and `refreshToken()` (used by resend, revives expired invites). Token is a `Str::random(64)` generated in `creating()`, unique-indexed; expiry defaults to 7 days. No global scope hides accepted invites — history is visible and filtered in the table.
- `App\Mail\UserInviteMail` (queued markdown mailable) — branded via `config('branding.org_name')`, contains the panel register URL with the token and the expiry date. `resources/views/mail/user-invite.blade.php`.
- `InviteFactory` with `expired()`, `accepted()`, `role()` states.

## Filament resources (admin-only, "Users" nav group)

- `UserResource`: table (name, email, role badge, created_at); form (name, unique email, single-role select saved via `syncRoles()`, password required on create / optional on edit). The role select validates against last-admin demotion. `EditUser` adds a "Send password reset link" header action (mirrors Filament's own broker flow so the link targets the panel reset route) and a `DeleteAction` guarded by `UserPolicy`.
- `InviteResource`: create form (email validated as not-already-a-user and no pending duplicate; role select) which emails the invite immediately and stamps `invited_by`; table (email, role, inviter, status badge, expires_at) with **Resend** (refresh token/expiry + re-send, works on expired invites) and **Revoke** (delete) actions.
- Both registered under a `NavigationGroup::make('Users')` in `AdminPanelProvider`; hidden from non-admins by their policies.

## Registration, settings & console

- `App\Filament\Pages\Auth\Register` (extends Filament's `Register`): reads `#[Url] $token`. On mount it resolves a usable invite (prefilling + locking the email), or — with no token — requires `open_registration` to be on, otherwise redirects to login with a notice. `handleRegistration()` assigns the invite's role (or `viewer` for open registration) and stamps `accepted_at`; it rejects an invite whose email was registered in the meantime.
- Panel now calls `->registration(Register::class)` and `->passwordReset()`.
- `SiteSetting` gained an `open_registration` boolean (migration + fillable + `instance()` default + a toggle on the Site Options page).
- `php artisan user:set-role {email} {role} {--force}` — validates against `UserRole`, applies via `syncRoles()`, refuses to demote the last admin without `--force`.
- Local seeders are now role-aware: `TestSeeder` assigns admin/editor to its two users; `ExampleDataSeeder` ensures a documented admin (`admin@example.com` / `password`).
- `.env.example` notes that `MAIL_*` must be configured for invites and password resets.

## Tests

Added 50 tests (all passing), covering: content/user/invite policies and role-less-user behaviour; invite lifecycle (token/expiry generation, resend, duplicate/existing-email rejection, status); registration (invite role assignment + acceptance stamping, expired/used/garbage token and invite-only redirects, open-mode viewer, already-registered-email rejection); password reset (route registration, request-flow notification, admin-triggered reset); `user:set-role`; and admin-only access to the management pages.

Test-harness changes: `User` factory gained `admin()`/`editor()`/`viewer()` states; `actingAsAdmin()` now grants the admin role and `actingAsEditor()`/`actingAsViewer()` were added; a global `beforeEach` resets spatie's permission cache. Roles exist in the test DB via the data migration.

## Known pre-existing failures (not introduced here)

The suite had **10 failing tests before this work** (verified by running the suite with all changes stashed): 5 in `Trove/SlugGenerationTest`, `PublicationStateEnumTest > maps each case to a label`, `PanelAccessTest > renders the custom Site Options and Site Content pages`, `SiteContentPageTest > persists translatable content keys`, and `Trove/CrudTest` create + publish. The last three stem from a missing `fluentui-*` icon set in the test environment / slug generation, unrelated to user management. This change set adds 50 passing tests and introduces **zero** new failures (176 passed / 10 pre-existing failures / 1 skipped).
