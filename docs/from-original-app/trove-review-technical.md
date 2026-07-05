# Trove Review & Publishing — Technical Manual

This document describes, at the code level, how the Trove draft / review / publish workflow currently works. It is a factual reference describing what exists, intended as preparation for a later code review. It does not propose changes or assess correctness.

All file paths are relative to the repository root. Line numbers refer to the state of the code at the time of writing and may drift.

## 1\. Components at a glance

| Concern | Where |
| --- | --- |
| The reviewed entity | `app/Models/Trove.php` |
| Admin CRUD + the "Check" wizard step | `app/Filament/Resources/TroveResource.php` |
| Create / Edit / List page classes | `app/Filament/Resources/TroveResource/Pages/` |
| App-specific draftable Edit behaviour | `app/Filament/Draftable/Pages/Edit/Draftable.php` |
| App-specific in-form "save draft" button | `app/Filament/Draftable/Forms/Components/Actions/SaveDraftFormAction.php` |
| Draft/revision/publish engine | `packages/laravel-drafts/` (submodule, `Oddvalue\LaravelDrafts`) |
| Filament integration for drafts | `packages/filament-drafts/` (submodule, `Guava\FilamentDrafts`) |
| Review commenting | `parallax/filament-comments` (Composer dependency) |
| DB schema | `database/migrations/2023_11_24_132900_create_troves_table.php` |
| Public + preview routes | `routes/web.php` |

Two local Composer path-repositories provide the engine (see `composer.json`): `guava/filament-drafts` (`dev-main`, `./packages/filament-drafts`) and `odd-value/laravel-drafts` (`./packages/laravel-drafts`). Supporting packages: `awcodes/shout` (the info/warning callout boxes in the form) and `parallax/filament-comments`.

## 2\. Data model

### 2.1 The `Trove` model

`app/Models/Trove.php` composes the workflow from several traits (lines 25–33):

```php
class Trove extends Model implements HasMedia
{
    use HasDrafts;            // Oddvalue\LaravelDrafts\Concerns\HasDrafts — draft/revision/publish engine
    use HasFactory;
    use HasFilamentComments;  // Parallax\FilamentComments — review comment threads
    use HasTranslations;      // Spatie — JSON translatable fields
    use InteractsWithMedia;   // Spatie Media Library
    use Searchable;           // Laravel Scout / Meilisearch
    use SoftDeletes;
}
```

Relations relevant to review (`app/Models/Trove.php:166-184`):

*   `user()` → `belongsTo(User::class, 'uploader_id')` — the uploader.
*   `uploader()` → `belongsTo(User::class)` (uses default `uploader_id`).
*   `checker()` → `belongsTo(User::class, 'checker_id')` — the person asked to check.
*   `requester()` → `belongsTo(User::class, 'requester_id')` — the person who asked.

`hasPublishedVersion()` (lines 212–217) is an accessor exposing `has_published_version`, defined as `$this->revisions()->where('is_published', true)->exists()`. It is used by the form to switch the publish button label.

Draftable relations are declared (lines 54–58) and are cloned when drafting/publishing:

```php
protected array $draftableRelations = [
    'tags',
    'troveTypes',
    'collections',
];
```

`$casts` (lines 35–45) includes `'check_requested' => 'boolean'`. Note that the review workflow in the form keys off `checker_id` / `requester_id`, not a `check_requested` column — and no migration defines a `check_requested` column (the cast is present on the model regardless).

### 2.2 The `troves` table

`database/migrations/2023_11_24_132900_create_troves_table.php` defines the schema. The draft columns come from the `drafts()` Blueprint macro (line 32), and the review columns are added explicitly (lines 33–34):

```php
$table->drafts();
$table->foreignId('requester_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
$table->foreignId('checker_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
```

The `drafts()` macro is registered in `packages/laravel-drafts/src/LaravelDraftsServiceProvider.php:35-56` and adds:

*   `uuid` (nullable) — the key that groups all versions of one logical Trove.
*   `published_at` (nullable timestamp).
*   `is_published` (boolean, default `false`).
*   `is_current` (boolean, default `false`).
*   `publisher_id` / `publisher_type` (nullable morphs) — who published.
*   a composite index on `[uuid, is_published, is_current]`.

Column names are configurable via `config/drafts.php` but the defaults above are used.

### 2.3 Key concepts: `uuid`, `is_current`, `is_published`

Every logical Trove is a set of rows sharing one `uuid`. Among those rows:

*   `**is_current**` marks the single latest working version (the one the admin edits).
*   `**is_published**` marks the single version that is publicly live.

These are independent flags. A logical Trove can have a published row _and_ a separate current draft row at the same time (this is exactly the "editing a live Trove" case). The relationship that ties versions together is `revisions()` (`packages/laravel-drafts/src/Concerns/HasDrafts.php:428-431`):

```php
public function revisions(): HasMany
{
    return $this->hasMany(static::class, $this->getUuidColumn(), $this->getUuidColumn())->withDrafts();
}
```

## 3\. The draft / revision engine (`laravel-drafts`)

The engine lives in `packages/laravel-drafts/src/Concerns/HasDrafts.php` and `Publishes.php`.

### 3.1 Model event wiring

`bootHasDrafts()` (`HasDrafts.php:45-85`) registers the lifecycle:

*   `**creating**` (lines 53–60): set `is_current = true`, set publisher, generate `uuid`; if `is_published` is not explicitly `false`, call `publish()`.
*   `**updating**` (lines 62–64): call `newRevision()` — snapshots the pre-update state as history.
*   `**publishing**` (lines 66–68): call `setLive()`.
*   `**deleted**` (lines 70–72): soft-delete all revisions.
*   `**restored**` **/** `**forceDeleted**`: cascade restore / force-delete to revisions.

A global scope `onlyCurrentInPreviewMode` (lines 47–51) limits queries to current rows when preview mode is on.

### 3.2 Creating a historical revision — `newRevision()`

`HasDrafts.php:87-121`. On every update (unless revisions are disabled, the model opted out via `withoutRevision()`, or it is being soft-deleted/restored), it replicates the _fresh_ (pre-update) model and, in a `saved` hook, writes that copy back with `is_current = false`, `is_published = false`, saved quietly so timestamps are preserved. The row being saved keeps its primary key and becomes the new state; the prior state is preserved as a non-current, non-published revision. `pruneRevisions()` then trims history.

### 3.3 Saving a draft — `saveAsDraft()` / `updateAsDraft()`

`HasDrafts.php:263-336`.

*   `updateAsDraft(array $attributes)` (329–336): `fill($attributes)` then `saveAsDraft()`.
*   `saveAsDraft()` (263–286): fires `savingAsDraft` + `saving`; **replicates** the model into a new row with `published_at = null`, `is_published = false`; calls `setCurrent()`; saves. If `config('drafts.clone_relations')` is true (it is — `config/drafts.php`), it calls `replicateAndAssociateDraftableRelations()` to copy the draftable relations onto the new draft. It then fires the `**drafted**` event and calls `pruneRevisions()`.

The net effect: a published row is left untouched and stays live, while a _separate_ current draft row holds the edits.

`setCurrent()` (148–163) sets `is_current = true` on this row and, in a `saved` hook, clears `is_current` on all sibling revisions.

### 3.4 Publishing — `publish()` and `setLive()`

`publish()` is in the `Publishes` trait (`packages/laravel-drafts/src/Concerns/Publishes.php:43-60`): it fires the `publishing` event (which triggers `setLive()`), then fires `published` in a `saved` hook.

`setLive()` (`HasDrafts.php:165-204`) is the core of publishing and has two paths:

1.  **No existing published revision, or this row** _**is**_ **the published one** (lines 167–174): set `published_at` (if unset), `is_published = true`, `setCurrent()`, and return. The current row becomes the live row.
2.  **A** _**different**_ **published revision already exists** (lines 176–204): the engine **swaps attributes** between the two rows (excluding the primary key). The existing published row is force-filled with the new attributes; the draft row is force-filled with the old published attributes. In a `saved` hook the published row is marked `is_published = true`, `is_current`, saved quietly, and `replicateAndAssociateDraftableRelations()` syncs the draftable relations onto it. The draft row is reset to `is_published = false`, `published_at = null`, `is_current = false`, with timestamps and revisioning disabled.

The practical consequence of path 2: **the canonical published row keeps a stable primary key**, and its _content_ is replaced with the edited version, while the edited draft row is demoted to a historical revision holding the previous published content. This is why a Trove's public URL/identity is stable across re-publishes.

### 3.5 Relation cloning — `replicateAndAssociateDraftableRelations()`

`HasDrafts.php:207-256`. Iterates `getDraftableRelations()` (for `Trove`: `tags`, `troveTypes`, `collections`). For `BelongsToMany` / `MorphToMany` relations (which is what all three are), it `sync()`s the target row's pivot to the source's related IDs (lines 248–253). `HasOne`/`HasMany` branches exist but are not exercised by `Trove`.

### 3.6 Media cloning on `drafted`

Media is **not** a draftable relation, so the `Trove` model handles it explicitly. `Trove::booted()` (`app/Models/Trove.php:60-106`) listens for the custom `drafted` event:

```php
static::registerModelEvent('drafted', function ($trove) {
    $draft = $trove->revisions()->where('is_current', true)->first();
    $trove->getRegisteredMediaCollections()->each(function (MediaCollection $collection) use ($trove, $draft) {
        $trove->getMedia($collection->name)->each(function (Media $media) use ($draft) {
            $media->copy($draft, $media->collection_name, $media->disk);
        });
    });
});
```

So whenever a draft is created, the source's media (per-locale cover images and content files — registered in `registerMediaCollections()`, lines 109–116) are copied onto the new current draft revision.

`Trove::booted()` also registers a `saving` hook (lines 77–104) that generates a unique `slug` (only when one is not already set), checking uniqueness across `withTrashed()->withDrafts()`.

### 3.7 Pruning — `pruneRevisions()`

`HasDrafts.php:356-373`. Keeps the most recent `config('drafts.revisions.keep')` draft revisions (currently **10**, per `config/drafts.php`), always retaining the current and published rows. Excess revisions are deleted.

### 3.8 Query scopes — `PublishingScope`

`packages/laravel-drafts/src/Scopes/PublishingScope.php`. The global scope (lines 19–25) constrains **all** queries to `is_published = 1` unless preview mode or "with drafts" mode is enabled. It adds these builder macros:

*   `withDrafts()` — removes the global scope (includes drafts).
*   `withoutDrafts()` — removes the global scope but re-adds `is_published = 1`.
*   `onlyDrafts()` — removes the global scope and adds `is_published = 0`.
*   `published()` — alias selecting published rows.

Plus scopes on the trait: `current()` (`withDrafts()->where('is_current', true)`), `withoutCurrent()`, `excludeRevision()` (`HasDrafts.php:449-462`).

This is why the public site and any default Eloquent query see only published rows, while the admin panel must opt in with `withDrafts()`.

## 4\. The Filament admin resource

### 4.1 Resource setup

`app/Filament/Resources/TroveResource.php:44-50`:

```php
class TroveResource extends Resource
{
    use Translatable;
    use Draftable;            // Guava\FilamentDrafts\Admin\Resources\Concerns\Draftable
    use InteractsWithScout;
    protected static ?string $model = Trove::class;
}
```

The `Draftable` concern (`packages/filament-drafts/src/Admin/Resources/Concerns/Draftable.php:23-30`) overrides `getEloquentQuery()` to call `->withDrafts()`, so the admin panel sees drafts and published rows alike.

### 4.2 The form wizard

`TroveResource::form()` (lines 63–372) builds a `Wizard` with five steps: **Details** (72), **Tags** (150), **Content** (162), **Cover Image** (237), **Check** (259). The wizard is `->skippable(fn(Component $livewire) => $livewire instanceof EditRecord)` (line 369) — steps are freely navigable when editing, sequential when creating.

### 4.3 The "Check" step — the review/publish control

`TroveResource.php:259-367`. This is the central UI of the workflow.

**Intro callout** (lines 262–269): a `Shout` info box explaining "Review and Publish". Its text states that publishing will notify the resources team (see §8 on what actually happens).

**The** `**next_steps**` **radio** (lines 271–285):

```php
Forms\Components\Radio::make('next_steps')
    ->dehydrated(false)          // not persisted to the DB
    ->options([
        'save'    => 'Save the trove as a draft',
        'review'  => 'Request a Review / Check',
        'publish' => 'Publish it!',
    ])
    ->live();
```

It is `dehydrated(false)` (purely a UI switch, never stored) and `live()` (re-renders the form on change). It controls the visibility of three fieldsets in the grid below (lines 287–366).

**Fieldset A — "Save as Draft"** (lines 293–301, visible when `next_steps === 'save'`): a `Shout` plus a `SaveDraftFormAction` (the app's in-form draft button — §5.3).

**Fieldset B — "Check Request"** (lines 302–322, visible when `next_steps === 'review'`):

```php
Forms\Components\Hidden::make('requester_id')
    ->formatStateUsing(fn(?Trove $record, Forms\Get $get) =>
        $get('checker_id') ? auth()->id() : $record?->requester_id),

Forms\Components\Select::make('checker_id')
    ->label('Select the person to ask')
    ->relationship('checker', 'name')
    ->live(),

Forms\Components\Actions::make([
    SaveDraftFormAction::make()->label('Save as Draft and Request Review'),
]),
```

*   `checker_id` is a live select bound to the `checker` relation (user name).
*   `requester_id` is a hidden field whose state is computed at render: if a `checker_id` is selected, it resolves to `auth()->id()` (the current user becomes the requester); otherwise it keeps the record's existing `requester_id`.
*   Saving uses the same `SaveDraftFormAction` (saves as draft), only re-labelled. So "requesting a review" is mechanically a draft-save that also persists `checker_id` and `requester_id`.

**Fieldset C — "Publish it"** (lines 323–365, visible when `next_steps === 'publish'`):

*   `Shout` `publish_it` (327–328): describes publishing.
*   `Shout` `are_you_sure` (330–333): `type('warning')`, visible when `!$record?->checker_id` — "no-one has been asked to check this trove".
*   `Shout` `are_you_sure_again` (335–338): `type('warning')`, visible when `$record?->requester_id === auth()->id()` — "you previously asked someone else to check…".
*   `Checkbox` `should_publish` (340–345): `dehydrated(false)`, `required`, `live`, visible when `!$record?->checker_id || $record?->requester_id === auth()->id()`. The explicit confirmation gate.
*   The publish action (348–362):

```php
Forms\Components\Actions\Action::make('Save and Publish')
    ->label(fn(?Trove $record) => $record?->has_published_version
        ? __('Save and Publish Changes')
        : __('Save and Publish'))
    ->disabled(fn(?Trove $record, Forms\Get $get) => !$record?->checker_id && !$get('should_publish'))
    ->action(function ($livewire) {
        $livewire->shouldSaveAsDraft = false;
        if ($livewire instanceof CreateRecord) { $livewire->create(); }
        if ($livewire instanceof EditRecord)   { $livewire->save(); }
    });
```

Behavioural summary of fieldset C:

*   The button label depends on `has_published_version` (§2.1).
*   The button is **disabled** when there is no checker _and_ the confirmation box is unticked. Equivalently: it is enabled when a checker is assigned, or when the user ticks `should_publish`.
*   The action sets `shouldSaveAsDraft = false` and then invokes the page's `create()` / `save()`, routing into the publish branch of the page traits (§5).
*   Because all visibility/disabled closures read `$record` (the persisted record), on a brand-new create `$record` is `null`: `!$record?->checker_id` is truthy, so the "are you sure" warning shows and the checkbox is required.

### 4.4 Tag fields

`getTagFields()` (lines 424–474) builds one multi-select per `TagType`. Not part of review, but these are draftable relations (`tags`) and therefore cloned on draft/publish.

### 4.5 Table actions

`table()` (lines 377–406) wires per-row actions (386–399):

*   `CommentsAction::make()` (from `parallax/filament-comments`) — opens the review comment thread for the Trove (§7).
*   `preview_trove` — a link to `/resources/{slug}` if `is_published`, else `/resources/preview/{slug}` (§6).
*   `EditAction`.

## 5\. The page classes

### 5.1 `CreateTrove`

`app/Filament/Resources/TroveResource/Pages/CreateTrove.php`. Uses `Guava\FilamentDrafts\Admin\Resources\Pages\Create\Draftable`. Its `handleRecordCreation()` (`packages/filament-drafts/src/Admin/Resources/Pages/Create/Draftable.php:16-26`) makes the model with `is_published = !$this->shouldSaveAsDraft` and saves with `->withoutRevision()`. For a publish, the `creating` event (`HasDrafts.php:53-60`) sees `is_published = true` and calls `publish()` → `setLive()` (no prior published row → becomes published + current).

`CreateTrove::getFormActions()` (lines 26–32) overrides the footer to **only** `SaveDraftAction` + Cancel. So the footer offers "Save Draft"; publishing a new Trove is done through the Check step's "Save and Publish" action.

`$canCreateAnother = false` (line 17).

### 5.2 `EditTrove`

`app/Filament/Resources/TroveResource/Pages/EditTrove.php`. Note line 17:

```php
// Use custom draftable trait because of https://github.com/GuavaCZ/filament-drafts/issues/15;
use \App\Filament\Draftable\Pages\Edit\Draftable;
```

It uses the **app's** Edit `Draftable` trait (§5.4), not Guava's. `getFormActions()` (lines 41–47) overrides the footer to `SaveDraftAction` + Cancel only. Header actions: `DeleteAction`. So, as with create, the publish path is the Check step's "Save and Publish".

### 5.3 `SaveDraftFormAction` and `SaveDraftAction`

Two near-identical actions both set `shouldSaveAsDraft = true` and then call `create()` / `save()`:

*   `app/Filament/Draftable/Forms/Components/Actions/SaveDraftFormAction.php` — a `Forms\Components\Actions\Action`, embeddable **inside the form schema** (used in the Check step fieldsets A and B).
*   `Guava\FilamentDrafts\Admin\Actions\SaveDraftAction` (`packages/filament-drafts/src/Admin/Actions/SaveDraftAction.php`) — a page-level footer action (used in `getFormActions()`).

### 5.4 The app's Edit `Draftable` trait — `handleRecordUpdate()`

`app/Filament/Draftable/Pages/Edit/Draftable.php`. This is a copy of Guava's Edit `Draftable` (`packages/filament-drafts/src/Admin/Resources/Pages/Edit/Draftable.php`) with one difference. `handleRecordUpdate()` (lines 31–61) has four branches:

```php
if ($record->isPublished() && $this->shouldSaveAsDraft) {
    $result = $record->updateAsDraft($data);                 // (1) edit a live Trove, save as draft
} elseif ($record->isPublished() && !$this->shouldSaveAsDraft) {
    $record->update($data);                                  // (2) edit a live Trove, save in place
} elseif (!$record->is_current && $this->shouldSaveAsDraft) {
    $record->updateAsDraft($data);                           // (3) edit an old revision, save as draft
} else {                                                     // (4) publish a draft (or current)
    if (!$this->shouldSaveAsDraft) {
        $record::withoutTimestamps(fn() => $record->revisions()
            ->where('is_published', true)
            ->update(['is_published' => false]));
        $record->publish();                                  // <-- present in app version, absent in Guava's
    }
    $record->update([
        ...$data,
        'is_published' => !$this->shouldSaveAsDraft,
    ]);
}
$this->dispatch('updateRevisions', $record->id);
```

Branch semantics:

1.  **Published + save-as-draft**: `updateAsDraft()` creates a new current draft from the edits; the live row stays published and unchanged.
2.  **Published + not draft**: `update()` edits the published row in place; the `updating` event snapshots the previous published content as a historical revision (§3.2).
3.  **Not current + save-as-draft**: editing a historical revision and saving it as the new current draft.
4.  **Else (publish path)**: if publishing, first unpublish any currently-published revisions (`is_published = false`), then call `$record->publish()`, then `update()` with `is_published = true`.

The only difference from Guava's stock trait is the explicit `$record->publish()` call in branch 4 (the file header cites GuavaCZ/filament-drafts issue #15 as the reason for the override). Guava's stock version omits that call and relies solely on the `is_published` flag in the subsequent `update()`.

`$shouldSaveAsDraft` defaults to `false` and is flipped to `true` by the SaveDraft actions, or forced to `false` by the "Save and Publish" action. The trait also customises the save-button label (`getSaveFormAction()`, lines 63–72) and the saved-notification title ("Draft saved" / "Published", lines 84–98), and registers the revisions paginator render hook (`renderingDraftable()`, lines 18–29). Note these page-level form-action overrides are largely superseded for `Trove` because `EditTrove::getFormActions()` replaces the footer (§5.2); the publish path is driven by the Check step action.

### 5.5 `ListTroves` — the review queue

`app/Filament/Resources/TroveResource/Pages/ListTroves.php`. `getTabs()` (lines 32–49) defines four tabs via query modifiers:

| Tab | Query | Meaning |
| --- | --- | --- |
| `all` | `where('is_current', true)` | every Trove's current working version |
| `published` | `withoutDrafts()` | only live rows (`is_published = 1`) |
| `drafts` | `onlyDrafts()->where('is_current', true)` | unpublished current drafts |
| `review` ("Check Requested") | `onlyDrafts()->where('is_current', true)->where('checker_id', '!=', null)` | current drafts that have a checker assigned |

The **Check Requested** tab is the de-facto review queue. It filters on `checker_id` being non-null but **not** on the current user — it shows the whole team's review queue, not just items assigned to the viewer.

Header actions: `CreateAction`, `LocaleSwitcher`.

## 6\. Preview and public routes

`routes/web.php` (within the `set.locale` group):

*   **Preview** (lines 34–42): `GET /resources/preview/{slug}`. Returns nothing if the user is not authenticated (`auth()->check()`); otherwise `Trove::withDrafts()->where('slug', $slug)->firstOrFail()` and renders the `trove` view. This is how a checker views an unpublished draft as it will appear on the site; the `withDrafts()` call bypasses the publishing global scope.
*   **Public** (lines 44–57): `GET /resources/{troveKey}` → `Trove::findBySlugOrRedirect($troveKey)`. That method (`app/Models/Trove.php:278-317`) only ever matches rows with `is_published = 1` (by slug, id, or `previous_slugs`), and 301-redirects to the canonical slug. So the public route never exposes drafts.

The `EditTrove` table's `preview_trove` action (`TroveResource.php:388-398`) routes to the public URL when `is_published`, else to the preview URL.

The drafts middleware `Oddvalue\LaravelDrafts\Http\Middleware\WithDraftsMiddleware` and the `Route::withDrafts()` macro exist (`LaravelDraftsServiceProvider.php:83-85`) but the Trove routes call `withDrafts()` explicitly on the query rather than via the route macro.

## 7\. Review commenting

`Trove` uses `Parallax\FilamentComments\Models\Traits\HasFilamentComments` (`app/Models/Trove.php:23,29`). The admin table exposes `CommentsAction::make()` per row (`TroveResource.php:387`). This provides a per-Trove comment thread used for review feedback between uploader and checker. Comments are stored by the `parallax/filament-comments` package (its own table/model); there is no app-level customisation of the comment workflow. The same trait/action pattern is also applied to `Collection` (`app/Models/Collection.php`, `app/Filament/Resources/CollectionResource.php`).

## 8\. Notifications — current behaviour

The "Check" step text (`TroveResource.php:262-269` and `327-328`) states that publishing "will send a notification to the resources team" and that requesting a review asks a team member to check.

In the current codebase there is no automated notification or email tied to these events:

*   No mailable or `Notification` class is dispatched on `publish`, on the `published`/`drafted` model events, or when `checker_id` / `requester_id` are set. A repository search for `Mail::`, `Mailable`, and `->notify(` finds only `app/Models/User.php`, which carries the standard Laravel `Notifiable` trait for auth flows (e.g. password reset / email verification), not Trove review.
*   The only Filament `Notification`s in the Trove area are the in-page toast on save ("Draft saved" / "Published", from the Edit `Draftable` trait) and the attach/detach toasts in `app/Livewire/AllTrovesTable.php` (the collection-builder UI).

The mechanisms that actually surface review state are therefore: the **Check Requested** list tab (`ListTroves`), the persisted `checker_id` / `requester_id` columns, and the **Comments** thread — not push notifications or email.

## 9\. End-to-end traces

### 9.1 New Trove → request a review

1.  User fills Details/Tags/Content/Cover, reaches **Check**, selects **Request a Review**, picks a `checker_id`, clicks "Save as Draft and Request Review".
2.  `SaveDraftFormAction::draft()` sets `shouldSaveAsDraft = true`, calls `CreateRecord::create()`.
3.  `Create\Draftable::handleRecordCreation()` makes the model with `is_published = false` (because `shouldSaveAsDraft`), `->withoutRevision()->save()`.
4.  `creating` event: `is_current = true`, publisher set, `uuid` generated; `is_published` is `false` so `publish()` is **not** called. `requester_id` (resolved to the current user because a `checker_id` was chosen) and `checker_id` are persisted.
5.  Result: one row, current + unpublished, with checker and requester set. It appears under **Drafts** and **Check Requested**.

### 9.2 Checker reviews

1.  Checker opens **Check Requested**, finds the Trove, clicks **Preview on Front-end** → `/resources/preview/{slug}` (auth-gated, `withDrafts()`), sees the draft rendered as the public page.
2.  Checker opens **Comments**, leaves feedback. Uploader edits and re-saves as draft (each save creates a new current draft and a pruned history; media is re-copied via the `drafted` listener).

### 9.3 Publish an edit to a live Trove

1.  User edits a published Trove, goes to **Check → Publish it**. If a checker is assigned, the button is enabled; otherwise they tick "I am sure…".
2.  "Save and Publish" sets `shouldSaveAsDraft = false`, calls `EditRecord::save()` → app `Edit\Draftable::handleRecordUpdate()`.
3.  The record is published and `shouldSaveAsDraft` is false → branch 4: currently-published revisions are set `is_published = false`, then `$record->publish()` fires `publishing` → `setLive()`.
4.  `setLive()` finds an existing published revision distinct from the edited row → **swaps attributes**: the canonical published row receives the new content and draftable relations; the edited row is demoted to a historical revision with the previous content. `update()` then writes `is_published = true`.
5.  `dispatch('updateRevisions', ...)` refreshes the revisions paginator. The public site now serves the new content at the unchanged slug; the prior version is retained in history (subject to the 10-revision prune).

## 10\. The revisions paginator

`packages/filament-drafts/src/Tables/Http/Livewire/RevisionsPaginator.php` renders the version list on the edit page (registered via `renderingDraftable()` in the Edit `Draftable` trait). It listens for the `updateRevisions` event, reloads `revisions()` ordered by `updated_at` desc, and computes counts of `published`, `drafts` (unpublished + current), and `revisions` (unpublished + not current). `switchVersion($url)` navigates between versions. This is the UI surface for the version history described in §3.