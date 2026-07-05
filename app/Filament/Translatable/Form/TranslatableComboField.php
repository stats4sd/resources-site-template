<?php

namespace App\Filament\Translatable\Form;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Concerns\CanBeCollapsed;
use Filament\Schemas\Components\Concerns\CanBeCompact;
use Filament\Schemas\Components\Concerns\HasDescription;
use Filament\Schemas\Components\Concerns\HasHeading;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Filament\Support\Concerns\HasIcon;
use Filament\Support\Concerns\HasIconColor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

//
class TranslatableComboField extends Field
{

    use CanBeCollapsed;
    use CanBeCompact;
    use HasDescription;
    use HasExtraAlpineAttributes;
    use HasHeading;
    use HasIcon;
    use HasIconColor;

    // NOTES:
    // Is a wrapper around a set of fields that all populate the same value in the database, but in different languages.


    protected string $view = 'filament.shared.forms.translatable-combo-field';

    public Closure|array|null $locales = null;

    protected function setUp(): void
    {
        parent::setUp();

        // populate the inner fields with the translations from the record
        $this->formatStateUsing(function (?Model $record, $state) {

            // if the record exists, and has a translation for this field, return the translations to populate the state
            if ($record && $record->{$this->getName()} && method_exists($record, 'getTranslations')) {
                return $record->getTranslations($this->getName());
            }

            return $state;
        });
    }

    public function locales(Closure|array|null $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    public function getLocales(): array
    {
        // default to the app locales
        if (!$this->locales) {
            return config('app.locales');
        }

        return $this->evaluate($this->locales);
    }

    public function getDescription(): string|Htmlable|null
    {
        // if no description is set, check if there is a 'hint'
        // Used so that this field can be a drop-in replacement for "Section" components.
        return $this->evaluate($this->description) ?? $this->getHint();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->evaluate($this->heading) ?? $this->getLabel();
    }


    /*
     * Set the child field. The given field will be duplicated for each locale.
     * @param Closure|string|Field $childField - either the FQDN of a Field class, or a Field instance. If a field instance is given its properties will be copied for each locale (except for name, label, and statePath)
     */
    public function childField(Closure|string|Field $childField = null): static
    {
        // check that $childField is a class that extends Form Field
        $childField = $this->evaluate($childField);

        if (is_string($childField) &&
            (!class_exists($childField) || !is_subclass_of($childField, Field::class)
            )
        ) {
            abort(501, 'Invalid field type: The childField for this TranslatableComboField must be a FQDN of a class that extends Filament\Forms\Components\Field (e.g. `TextInput::class`');
        }

        $localeFields = [];

        // create a field for each locale
        foreach ($this->getLocales() as $locale => $localeLabel) {


            // clone the childField properties
            if ($childField instanceof Field) {
                $newField = clone $childField;
                // set the name and label based on the locale.
                $newField->label($localeLabel);
                $newField->statePath($locale);
            } else {
                // create a new field instance using the given FQDN
                $newField = $childField::make($locale)
                    ->label($localeLabel);
            }

            $localeFields[] = $newField;
        }


        // check if the field is required - if yes, add requiredIf rules to ensure at least one locale is filled.
        if ($this->isRequired()) {


            $localeFields = collect($localeFields)
                ->map(fn(Field $field) => $this->makeFieldRequiredWithoutAll($field, $localeFields))
                ->toArray();
        }

        // Propagate an explicitly non-dehydrated prototype field onto the combo itself.
        // isDehydrated() returns false early for non-dehydrated fields; only a dehydrated
        // field reaches the container-dependent visibility check, which throws on a detached
        // prototype in Filament 5 — treat that (the default) as dehydrated and do nothing.
        try {
            $childIsDehydrated = ! ($childField instanceof Field) || $childField->isDehydrated();
        } catch (\Throwable) {
            $childIsDehydrated = true;
        }

        if (! $childIsDehydrated) {
            $this->dehydrated(false);
        }

        $this->childComponents($localeFields);
        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        // Read the raw stored child components rather than getChildComponents(), which in
        // Filament 5 resolves a Schema needing an initialized container that isn't available
        // at schema-definition time (when required() is chained on).
        $children = $this->getDefaultChildComponents();

        // if the child components exist before required() is called, apply the required rule to each child component.
        if ($condition && is_array($children) && count($children)) {

            // update child components with required rule
            $this->childComponents(
                collect($children)
                    ->map(fn(Field $field) => $this->makeFieldRequiredWithoutAll($field, $children))
                    ->toArray()
            );
        }

        return parent::required($condition);
    }

    public function makeFieldRequiredWithoutAll(Field $field, $localeFields)
    {
        $otherFields = collect($localeFields)
            ->filter(function (Field $otherField) use ($field) {
                return $otherField !== $field;
            })
            ->map(function (Field $otherField) {
                return $otherField->statePath;
            });

        return $field
            ->requiredWithoutAll($otherFields->toArray());
    }
}
