<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteOptionsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Site Options';

    protected static ?string $title = 'Site Options';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = SiteSetting::instance();
        $this->form->fill([
            'show_language_filter' => $settings->show_language_filter,
            'show_trove_type_filter' => $settings->show_trove_type_filter,
            'open_registration' => $settings->open_registration,
            'locales' => $settings->locales ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Features')
                    ->schema([
                        Forms\Components\Toggle::make('show_language_filter')
                            ->label('Show language filter in Browse Library')
                            ->helperText('When disabled, the language filter is hidden even if multiple languages are configured.'),
                        Forms\Components\Toggle::make('show_trove_type_filter')
                            ->label('Show resource type filter in Browse Library')
                            ->helperText('When disabled, the resource type filter is hidden.'),
                        Forms\Components\Toggle::make('open_registration')
                            ->label('Allow open registration')
                            ->helperText('When enabled, anyone can register from the login page. New registrants get read-only (viewer) access until an admin promotes them. When disabled, registration is invite-only.'),
                    ]),
                Section::make('Languages')
                    ->schema([
                        Forms\Components\Repeater::make('locales')
                            ->label('Supported languages')
                            ->helperText('Add or remove languages. Changes take effect immediately after saving.')
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Language code')
                                    ->placeholder('en')
                                    ->required()
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('label')
                                    ->label('Language name')
                                    ->placeholder('English')
                                    ->helperText('Write the name in that language e.g. "Español" or "Français".')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add language')
                            ->reorderable(false)
                            ->defaultItems(0),
                    ]),
            ])
            ->statePath('data');
    }

    // v5 renders the page through content(); wrap the form schema in a submit form
    // with the Save action in its footer (replaces the old site-options.blade view).
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = SiteSetting::instance();
        $settings->update([
            'show_language_filter' => $data['show_language_filter'],
            'show_trove_type_filter' => $data['show_trove_type_filter'],
            'open_registration' => $data['open_registration'],
            'locales' => $data['locales'],
        ]);
        Notification::make()->success()->title('Settings saved')->send();
    }

    protected function getFormActions(): array
    {
        return [
            PageAction::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
