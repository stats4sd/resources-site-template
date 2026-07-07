# User "Set Your Password" Email on Creation

Implements [docs/plans/user-set-password-on-create.md](../plans/user-set-password-on-create.md).

Adds an option to the admin **Create User** flow to email a new user an expiring, single-use link to set their own password, instead of an admin typing one in. Modelled on the existing invite system but without a registration step — the user already exists; the link only sets a password.

## Behaviour

- The Create User form now has a **Password** choice: *"Email the user a link to set their own password"* (default) or *"Set a password now"*. The password/confirmation fields only appear (and are only required) for the manual option.
- Choosing the email option creates the user with a `null` password (no usable credential) and emails a `SetPasswordMail` containing a link to `/admin/set-password?token=…`.
- The link opens a public page (Filament reset-password styling) where the user sets a password; on success the token is consumed and they're redirected to login.
- Tokens are 64-char, single-use, and expire after 7 days (`PasswordSetup::EXPIRY_DAYS`), mirroring `Invite`.
- The **Edit User** page gains a *"Resend set-password link"* action, shown only while the user still has no password; it refreshes the token (invalidating older links) and re-emails.

## Files added

- `database/migrations/2026_07_06_160000_create_password_setups_table.php` — `user_id` (unique, cascade), `token` (unique 64), `expires_at`, `used_at`.
- `app/Models/PasswordSetup.php` — token/expiry generation on create, `scopePending`, `isUsable`/`isExpired`, `refreshToken`, `markUsed`. Analogue of `Invite`.
- `database/factories/PasswordSetupFactory.php` — with `expired()` / `used()` states.
- `app/Mail/SetPasswordMail.php` + `resources/views/mail/set-password.blade.php` — queued mail; URL built via `Filament::getPanel('admin')->route('auth.set-password', …)`.
- `app/Filament/Pages/Auth/SetPassword.php` — extends Filament's `ResetPassword`, swapping the Laravel password-reset broker for the `PasswordSetup` token (validates on mount and re-validates on submit; rejects invalid/expired/used tokens and already-authenticated visitors).
- `tests/Feature/PasswordSetup/PasswordSetupTest.php` — 13 cases covering the model, both create paths, the set-password page (happy path, invalid/expired/used, expiry-between-load-and-submit, already-authenticated), and resend.

## Files changed

- `app/Providers/Filament/AdminPanelProvider.php` — registers the unauthenticated `/set-password` route via `->routes()` (runs in the panel's public route group, before the auth-middleware group), named `filament.admin.auth.set-password`.
- `app/Filament/Resources/UserResource.php` — adds the `password_method` radio (create-only, `dehydrated(false)`, `live()`); password fields made conditional on it.
- `app/Filament/Resources/UserResource/Pages/CreateUser.php` — nulls the password and sends `SetPasswordMail` (creating a `PasswordSetup`) when the email option is chosen; adjusts the created-notification title.
- `app/Filament/Resources/UserResource/Pages/EditUser.php` — adds the resend action + `resendSetPasswordLink()`.
- `tests/Feature/Filament/UserResourceTest.php` — the manual-password create test now opts into `password_method = manual` (the form default changed to the email option).

## Notes

- Distinct from `EditUser`'s existing *"Send password reset link"* (Laravel broker, for accounts that already have a password). The new flow is for *initial* setup and keeps its own 7-day expiry independent of `config('auth.passwords.users.expire')`.
- `users.password` was already nullable, so email-link users hold no credential until they set one.
- The full suite has 10 pre-existing failures on this branch (Trove slug/publication/site-content/panel-access) unrelated to this change; all user/invite/password-setup tests pass.
