# EditTrove "No changes to save" guard never fires — every live save forks a draft

**Date Reported**: 2026-07-03
**Status**: Open
**GitHub Issue**: https://github.com/stats4sd/resources-site-template/issues/10

## Problem

`EditTrove` tries to avoid forking a throwaway shadow draft when a live trove is saved with no actual changes, by snapshotting the form state in `afterFill()` and comparing at save time:

```php
protected array $originalFormState = [];

protected function afterFill(): void
{
    $this->originalFormState = $this->troveFormStateSnapshot();
}

protected function troveFormIsDirty(): bool
{
    return $this->troveFormStateSnapshot() !== $this->originalFormState;
}
```

`$originalFormState` is a **protected** property, so Livewire does not persist it across the request round-trip. `afterFill()` runs only on the initial page load; by the time `save()` runs (a separate Livewire request) `$originalFormState` has reset to `[]`. `troveFormIsDirty()` therefore always compares the current (non-empty) state against `[]` and is always true.

Net effect: the "No changes to save" notification never appears, and **every** plain Save of a live trove forks a shadow draft — even when nothing was edited.

Surfaced by `tests/Feature/Filament/Trove/CrudTest.php` — the "does not fork a draft on a plain save with no changes" test is currently `->skip()`ed with a pointer to this issue.

## Location

`app/Filament/Resources/TroveResource/Pages/EditTrove.php` — `$originalFormState`, `afterFill()`, `troveFormIsDirty()`, `save()`.

## Expected Behavior

A plain Save that changed nothing should notify "No changes to save" and not fork a draft. The baseline snapshot needs to survive the round-trip (e.g. make `$originalFormState` a public Livewire property, or recompute the baseline on the save request). Once fixed, un-skip the corresponding test.
