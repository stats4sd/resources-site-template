# Extra Notes: Trove Saving

### every save of an existing Trove inserts a new troves row

The "logical" Trove is a set of rows sharing one uuid. A save never overwrites-in-place-only; it always adds a row. The mechanism differs by branch, and growth is capped by pruning at 10 retained draft revisions.

#### 1.a — Non-published draft (no published version exists yet)

The edited record is is_published=false, is_current=true, so Draftable.php:31-56 falls through to branch 4 (else). On a draft save (shouldSaveAsDraft=true) the publish block is skipped and it runs $record->update([...$data, 'is_published'=>false]).

update() fires the updating event → newRevision() (HasDrafts.php:87-121), which replicate()s the pre-update content and, in a saved hook, inserts it as a new row (is_current=false, is_published=false, saved quietly to preserve timestamps). The original row keeps its PK and holds the new content.

→ +1 row per save — a historical snapshot of the previous content.

#### 1.b — Published version + a non-public is_current=true draft

Depends on which row the editor loaded (the ID in the URL / which list tab you came from), but it's yes either way:

Editing the current draft (is_current=true, is_published=false — the normal path; the All, Drafts, and Check Requested tabs all filter is_current=true): same as 1.a → branch 4 → update() → newRevision() → +1 historical revision row. The published row is untouched.

Editing the published row itself (is_published=true, e.g. from the Published tab): hits branch 1 (isPublished() && shouldSaveAsDraft) → updateAsDraft() → saveAsDraft() (HasDrafts.php:263-286), which replicate()s into a brand-new current draft row and setCurrent() demotes the existing draft. The live published row stays unchanged. → +1 row.

#### Caveats worth noting in review

- Creation is the exception: a brand-new Trove is saved with ->withoutRevision() (Create/Draftable), so the first save produces just the one row, no extra revision.
- Unbounded? No — pruneRevisions() (HasDrafts.php:356-373) trims to drafts.revisions.keep = 10 non-current drafts, always retaining the current and published rows. So rows accumulate up to that cap, then the oldest draft revisions are hard-deleted on each save.
- Because relations are cloned (clone_relations=true) and the drafted/revision paths copy media + sync pivots, each new row also drags pivot rows and (on saveAsDraft) copied media — relevant if you're auditing growth beyond just the troves table.