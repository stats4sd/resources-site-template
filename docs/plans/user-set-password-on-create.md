# User "Set Your Password" Email on Creation

**Status:** Completed — see [docs/change-logs/user-set-password-on-create.md](../change-logs/user-set-password-on-create.md).

## Summary

Add an option to the admin **Create User** flow: instead of an admin typing a password, send the new user an email containing an expiring, single-use link to a public page where they set their own password. The user already exists (no registration step) — the link only sets a password. Modelled on the existing invite system (`Invite` model, `UserInviteMail`, tokenised `Register` page), but distinct from it because there is no registration and no role selection at the link stage.

## Decisions (assumed — state and proceed)

- **Dedicated token model, not the Laravel password-reset broker.** Per the request ("similar to the invite system, user-unique, expiring token"). A new `PasswordSetup` model/table mirrors `Invite`, keeping expiry (7 days) independent of Laravel's global password-reset expiry (60 min) and of real reset flows.
- **Alternative considered:** `EditUser` already sends a reset link via `Password::broker()->sendResetLink()` ([EditUser.php:83](../../app/Filament/Resources/UserResource/Pages/EditUser.php#L83)). We could reuse it for creation by lengthening `config('auth.passwords.users.expire')`. Rejected: it changes global reset behaviour, its page says "Reset password" and requires typing the email, and it doesn't match the invite-style pattern asked for. The two flows stay separate; the new one is for *initial* setup, the existing one for *resetting* an established account.
- **`password` column is nullable** (verified in `2014_10_12_000000_create_users_table.php`). Email-link users are created with `password = null` — no usable credential exists until they set one; login attempts fail safely against null.
- **One active setup per user** — `password_setups.user_id` is unique; "resend" refreshes the token (mirrors `Invite::refreshToken()`).
- **After setting the password, redirect to the login page** with a success notification (mirrors Filament's reset flow), rather than auto-login. Simpler and safer.
- **Default create-mode: email-link.** The toggle defaults to "email the user a link" (admin never handles a password); "set a password now" remains available. One-line change if we'd rather default to manual.

## Files to add

| File | Purpose |
|---|---|
| `database/migrations/xxxx_create_password_setups_table.php` | `id`, `user_id` (FK→users, `unique`, `cascadeOnDelete`), `token` (string 64, unique), `expires_at`, `used_at` (nullable), timestamps. |
| `app/Models/PasswordSetup.php` | Mirrors `Invite`: `EXPIRY_DAYS = 7`; `booted()` generates token + expiry on create; `generateToken()`, `user()` belongsTo, `scopePending()`, `isUsable()`, `isExpired()`, `refreshToken()`, `markUsed()`. No status enum / Filament resource — it's an internal artifact, not browsable. |
| `app/Mail/SetPasswordMail.php` | Mirrors `UserInviteMail`. Subject `"Set your password for :org"`. Builds URL to the new page with `?token=…`. Implements `ShouldQueue`. |
| `resources/views/mail/set-password.blade.php` | Markdown mail, mirrors `user-invite.blade.php`: greeting, "set your password" button, expiry date, ignore-if-unexpected line. |
| `app/Filament/Pages/Auth/SetPassword.php` | Public (unauthenticated) Filament simple page — the "set your password" screen. |

## Files to change

| File | Change |
|---|---|
| `app/Filament/Resources/UserResource.php` | Add a `Radio`/`Toggle` `password_method` field (`dehydrated(false)`, `visible` only on `create`) with options `email_link` / `manual`. Make `password` + `passwordConfirmation` `visible` and `required` only when `password_method === 'manual'` on create; edit behaviour unchanged. |
| `app/Filament/Resources/UserResource/Pages/CreateUser.php` | In `mutateFormDataBeforeCreate`: if email-link, set `$data['password'] = null` (unset manual fields). In `afterCreate` (after `syncRoles`): if email-link, create a `PasswordSetup` for the user and `Mail::to(...)->send(new SetPasswordMail(...))`; adjust `getCreatedNotificationTitle()` accordingly. `$this->data['password_method']` is available (dehydrated field, same mechanism as `role`). |
| `app/Providers/Filament/AdminPanelProvider.php` | Register the page's unauthenticated route via the panel's `->routes()` closure: `Route::get('/set-password', SetPassword::class)->name('...set-password')`, so it inherits panel middleware but **not** `authMiddleware`. |
| `app/Filament/Resources/UserResource/Pages/EditUser.php` *(optional)* | Add a "Resend set-password link" header action, shown only when the user has `password === null` (never set one): `refreshToken()` + re-email. Distinct from the existing "Send password reset link". |

## The SetPassword page

Extends Filament v5's auth simple-page base (auth pages live under `Filament\Auth\Pages\` in this repo — see `Register`). Structure mirrors `Register` ([Register.php](../../app/Filament/Pages/Auth/Register.php)):

- `#[Url] public ?string $token`, `public ?PasswordSetup $setup`, `public ?array $data` (form state).
- `mount()`: if already authenticated → redirect into the panel. Load `PasswordSetup::where('token', $token)->first()`; if missing or `!isUsable()` → redirect to login with a generic "This link is invalid or has expired." notification (don't reveal which). Fill an empty form.
- `form()`: `password` (revealable, `confirmed`, Filament's default password rule) + `passwordConfirmation`. No email field (identity comes from the token).
- `setPassword()` submit action: re-check `isUsable()` (guards expiry between load and submit); update the linked user's `password` (the model's `hashed` cast hashes it); `markUsed()`; success notification; redirect to login.
- Headings: "Set your password" / subheading naming the account email.

**Route/middleware note to verify during build:** confirm the `->routes()` closure registers the route in the panel's unauthenticated group (so the page renders with panel context but no auth gate), the same way `->registration()`/`->passwordReset()` expose `/admin/register` and `/admin/password-reset/*`. If `->routes()` proves to sit behind auth, fall back to registering the route in the panel's non-auth route group directly.

## Security

- 64-char high-entropy token is the sole credential (matches `Invite`).
- Single-use (`used_at`) + 7-day expiry; re-validated on submit.
- Unauthenticated page rejects already-authed sessions.
- Invalid/expired/used → generic redirect, no user-existence disclosure.
- Placeholder-free: email-link users have `password = null` until they set one.

## Tests (`tests/Feature/...`, mirror `InviteLifecycleTest`)

- Create user with email-link → `PasswordSetup` row created, `SetPasswordMail` sent (`Mail::fake`), user `password` null, cannot log in.
- Create user with manual password → unchanged behaviour, no mail, no `PasswordSetup`.
- Visit `/admin/set-password?token=valid` renders; submit → user password set (can log in), token `used_at` stamped.
- Invalid / expired / used / already-authenticated token → redirect to login with notification, password unchanged.
- Resend (if implemented) → old token invalidated, new one usable.

Use `Livewire::test(CreateUser::class)` and `Livewire::test(SetPassword::class)` with the `usePublicContext()`/panel test harness conventions already in the suite.

## Out of scope

- No `PasswordSetup` Filament resource / admin table (internal artifact).
- No change to the existing invite or reset-link flows.
