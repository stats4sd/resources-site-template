# User Management and Invites

**Status:** Completed — see [docs/change-logs/user-management-and-invites.md](../change-logs/user-management-and-invites.md).

## Summary

Add a user management system to the admin panel: a `UserResource` for CRUD, an email-based invite flow with tokenised registration, a three-tier role model (`viewer` / `editor` / `admin`) built on spatie/laravel-permission, an invite-only vs open-registration toggle, and self-service password reset. Azure/Socialment login has already been removed from the login page (commit `8c0f644`); its leftovers stay dormant.

## Decisions (agreed 2026-07-06)

- **Invites carry a role** — the inviting admin picks the role; the Register page assigns it on completion.
- **Invites expire after 7 days**, with a "resend" action that refreshes token + expiry.
- **Self-service password reset** enabled via Filament's built-in `->passwordReset()`.
- **Open registration ships behind a third read-only role**: open registrants start as `viewer` (no edit rights anywhere); admins promote them. Default mode is invite-only.
- **Roles via spatie/laravel-permission** (updated 2026-07-06, superseding the earlier enum-column decision). Three roles is not itself enough to warrant the package; the driver is future direction — giving individual app owners more control over users and permissions, with granular permissions and owner-defined roles later. For now only the three fixed roles exist and policies check roles directly; no granular permissions are defined yet.
- Invite URLs use a high-entropy random token as the credential (`Str::random(64)`, unique-indexed). No additional Laravel URL signature — it would be redundant with the token and complicate resends. (The original spec said "signed url"; this is the assumed interpretation.)

## Implementation context (verified 2026-07-06)

- **CLAUDE.md's stack section is stale**: it says Laravel 11 + Filament 3.2, but composer.json is `laravel/framework ^13.0` + `filament/filament ^5.0`. Use Filament v5 conventions throughout — auth pages live under `Filament\Auth\Pages\`, form pages use the `Schema`/`content()` pattern. `app/Filament/Pages/SiteOptionsPage.php` is the in-repo reference for a v5 settings form page.
- The reference package (stats4sd/filament-team-management) targets Filament 3 — use it as a pattern reference only (`#[Url] public string $token`, `Invite::where('token', …)->firstOrFail()` in `mount()`, prefilled read-only email field), don't copy code.
- `users.password` is already nullable (Azure-era); the panel already has `->profile()` so users can change their own password once logged in.
- The panel currently has no `->registration()` and no `->passwordReset()` — both are added by this plan.

## Role model

spatie/laravel-permission roles (`viewer`, `editor`, `admin`) on the `web` guard, teams feature off. `App\Enums\UserRole` (string-backed: `viewer`, `editor`, `admin`) remains as the typed source of role names — used for Filament selects, the Invite `role` column, and `hasRole(UserRole::Admin->value)`-style checks, so role names aren't magic strings.

| Capability | viewer | editor | admin |
|---|---|---|---|
| Access `/admin` panel | ✅ (read-only) | ✅ | ✅ |
| View troves/collections/tags/types | ✅ | ✅ | ✅ |
| Create/edit/delete/publish troves, collections, tags, tag types, trove types | ❌ | ✅ | ✅ |
| Site Options / Site Content pages | ❌ | ❌ | ✅ |
| Manage users + invites | ❌ | ❌ | ✅ |

"Regular user" from the original spec = `editor`. `viewer` exists so open registration grants nothing editable.

Helper methods on `User` (wrapping spatie's `HasRoles`): `isAdmin()`, `canEdit()` (editor or admin). Policies call these helpers, not `hasRole()` directly, so a future move to granular permissions (`can('manage troves')` etc.) only touches the helpers/policies, not every caller. `canAccessPanel()` stays `true` for all authenticated users (viewers get the read-only panel).

**Upgrade path for existing data**: a data migration (or the role seeder) assigns the `admin` role to all existing users (they currently have unrestricted access; this preserves behaviour). New users get no role by default — every creation path assigns one explicitly (UserResource select, invite role, `viewer` for open registration); treat a role-less user as `viewer`-equivalent (helpers return false).

## Implementation

### 1. Roles + policies

- `composer require spatie/laravel-permission`; publish config + migration. Delete the orphaned `database/migrations/permissions/2023_04_12_173431_create_permission_tables.php` (dead code that never runs) and use a freshly published migration in `database/migrations/` instead — the package's schema has changed since that 2023 copy.
- `User` gets `HasRoles` + the `isAdmin()`/`canEdit()` helpers.
- `App\Enums\UserRole` enum (implements `HasLabel` for Filament selects).
- `RoleSeeder` in `database/seeders/Prep/` (part of the base seed): `firstOrCreate` the three roles so it's idempotent on existing installs; reset the spatie permission cache after seeding. No permissions defined yet — roles only.
- Policies (auto-discovered): `TrovePolicy`, `CollectionPolicy`, `TagPolicy`, `TagTypePolicy`, `TroveTypePolicy` — `viewAny`/`view` for everyone, mutating abilities require `canEdit()`. `UserPolicy` + `InvitePolicy` — admin only, plus:
  - a user cannot delete themselves;
  - the last admin cannot be deleted or demoted (checked in `UserPolicy::delete()` and validated on the Edit form's role field).
- `SiteOptionsPage` / `SiteContentPage`: `canAccess()` → `isAdmin()` (also hides them from navigation for non-admins).
- Check `parallax/filament-comments` respects a policy; if it has its own gate config, restrict comment create/delete to `canEdit()`.

### 2. Invite model + mail

- Migration `create_invites_table`: `email` (indexed), `role` (string — a `UserRole` value naming the spatie role to assign on registration), `token` (string 64, unique), `invited_by` (FK users, nullable, nullOnDelete), `expires_at`, `accepted_at` (nullable), timestamps.
- `App\Models\Invite`: casts (`role` → `UserRole`, datetimes), `inviter()` belongsTo, `scopePending()` (`accepted_at` null and `expires_at` future), `status` accessor → Pending / Expired / Accepted (small `InviteStatus` enum or string), `isUsable()`, `refresh token + expiry` method used by resend. Token generated in `booted()::creating` via `Str::random(64)`. **No global scope** hiding accepted invites (the package's `is_confirmed` global scope made history invisible; a table filter does this better).
- `App\Mail\UserInviteMail` (markdown mailable): branded via `config('branding.org_name')`, contains the register URL (`route to /admin/register?token=…`) and expiry date. Queued (`ShouldQueue`) — falls back to sync queue driver locally.
- Factory + note in README/`.env.example`: `MAIL_*` must be configured for invites and password resets.

### 3. Filament resources (admin-only, "Users" nav group)

- `UserResource`: table (name, email, role badge from the user's spatie role, created_at); form (name, email unique, single-role select built from `UserRole` and saved via `syncRoles()`, optional "set password" field on create — required on create, hidden/optional on edit); actions:
  - **Edit** — change name/email/role (role change guarded against last-admin demotion);
  - **Reset password** — two options on the Edit page: a "Send password reset link" action (uses Laravel's broker) and an optional manual new-password field;
  - **Delete** — guarded by `UserPolicy` (no self-delete, no last-admin delete).
- `InviteResource`: create form (email — validated as not already a user and no pending invite for the same address; role select); table (email, role, inviter, status badge, expires_at); actions: **Resend** (refresh token/expiry, re-send mail — also usable on expired invites), **Delete** (revoke). Creating an invite sends the mail immediately.
- Register both under a `NavigationGroup::make('Users')` in `AdminPanelProvider`'s navigation builder (they won't appear for non-admins because of policy checks).

### 4. Registration page + mode toggle

- `SiteSetting`: add `open_registration` boolean (default `false`) — migration + `$fillable` + `instance()` defaults + a toggle in `SiteOptionsPage` ("Allow open registration" with helper text explaining registrants get read-only access).
- `App\Filament\Pages\Auth\Register` extends `Filament\Auth\Pages\Register`:
  - `#[Url] public ?string $token = null;`
  - `mount()`: if a token is present, resolve a usable invite or abort with a friendly notice → redirect to login ("This invitation is invalid or has expired."). If no token and `open_registration` is off, redirect to login. If invite found: prefill email, mark the field read-only.
  - `handleRegistration()` / user creation: `assignRole()` from the invite's `role`, or `viewer` for open registration; stamp `invite->accepted_at`; standard `Registered` event fires.
  - Duplicate-safety: if a user with the invite's email already exists (e.g. created manually after the invite went out), reject with a notice rather than erroring.
- `AdminPanelProvider`: `->registration(Register::class)` and `->passwordReset()`.
- Login page: leave as-is (Azure removal already done). Socialment composer dep, `connected_accounts` table and `resources/views/filament/socialment/` stay dormant for a future "enable SSO" site option — do not strip.

### 5. Console + seeding

- `php artisan user:set-role {email} {role}` command — bootstrap the first admin on a fresh deploy and recover from lockouts. Validates against `UserRole`, applies via `syncRoles()`, warns when demoting the last admin (refuses without `--force`).
- `Database\Seeders\Example\ExampleDataSeeder`: add an example admin user (documented credentials) so local dev has a working admin immediately.

### 6. Tests (Pest)

- **Invite lifecycle**: creating an invite generates a unique token + 7-day expiry and sends `UserInviteMail` (Mail fake); resend refreshes token/expiry; cannot invite an existing user's email or duplicate a pending invite.
- **Registration**: valid token registers a user with the invite's role and stamps `accepted_at`; expired/used/garbage token is rejected; token reuse after acceptance is rejected; invite-only mode with no token redirects to login; open mode registers a `viewer`; invite for an already-registered email is rejected.
- **Roles/policies**: viewer cannot create/update/delete troves/collections/tags (policy unit tests); editor can; only admin can access UserResource, InviteResource, SiteOptionsPage, SiteContentPage; self-delete and last-admin delete/demote are blocked.
- **Password reset**: reset routes are registered and the request flow sends the notification (Notification fake).
- Existing suite must stay green — the new policies are the risk point (previously every authenticated user could do everything); fix any tests that implicitly relied on that by giving their users `editor`/`admin` roles (`User` factory gets `admin()`/`editor()`/`viewer()` states via `afterCreating` + `assignRole()`). Roles must exist in the SQLite `:memory:` DB — seed them in the Pest base setup (or lazily `firstOrCreate` in the factory states) and remember spatie's permission cache between tests.

## Out of scope (deliberate)

- Email verification for registrants.
- Re-enabling Azure/SSO login (future site option; leftovers kept dormant).
- Admin notification when an open registrant signs up (can be added later if open mode gets real use).
- Granular permissions and owner-defined roles — the spatie foundation is laid now precisely so these can come later (per-site custom roles, a permission matrix UI); this phase ships the three fixed roles only.

## Suggested implementation order

1. spatie/laravel-permission install + role seeder + `UserRole` enum + `User` helpers + policies + page gating (+ factory states, fix existing tests).
2. UserResource (CRUD, guarded delete/demote, password reset actions) + `user:set-role`.
3. Invite model + mail + InviteResource.
4. Register page + `open_registration` setting + panel `->registration()`/`->passwordReset()`.
5. Tests throughout; change log on completion.
