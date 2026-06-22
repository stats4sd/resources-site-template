<?php

namespace App\Filament\Translatable\Form;

use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Concerns;
use Filament\Forms\Components\Contracts;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Concerns\CanBeCollapsed;
use Filament\Support\Concerns\HasDescription;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Filament\Support\Concerns\HasHeading;
use Filament\Support\Concerns\HasIcon;
use Filament\Support\Concerns\HasIconColor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Color\Contrast;

//
class TranslatableComboField extends Field implements Contracts\HasHeaderActions, Contracts\HasFooterActions
{

    use Concerns\CanBeCollapsed;
    use Concerns\CanBeCompacted;
    use Concerns\HasFooterActions;
    use Concerns\HasHeaderActions;
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

        if($childField instanceof Field && ! $childField->isDehydrated()) {
            $this->dehydrated(false);
        }

        $this->childComponents($localeFields);
        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {

        // if the child components exist before required() is called, apply the required rule to each child component.
        if ($condition && $this->getChildComponents()) {

            // update child components with required rule
            $this->childComponents(
                collect($this->getChildComponents())
                    ->map(fn(Field $field) => $this->makeFieldRequiredWithoutAll($field, $this->getChildComponents()))
                    ->toArray()
            );
        }

        return parent::required();
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
