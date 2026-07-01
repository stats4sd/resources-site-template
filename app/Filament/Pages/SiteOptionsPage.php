<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteOptionsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.site-options';

    protected static ?string $navigationLabel = 'Site Options';

    protected static ?string $title = 'Site Options';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public array $data = [];

    public function mount(): void
    {
        $settings = SiteSetting::instance();
        $this->form->fill([
            'show_language_filter' => $settings->show_language_filter,
            'locales' => $settings->locales ?? [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Features')
                    ->schema([
                        Forms\Components\Toggle::make('show_language_filter')
                            ->label('Show language filter in Browse Library')
                            ->helperText('When disabled, the language filter is hidden even if multiple languages are configured.'),
                    ]),
                Forms\Components\Section::make('Languages')
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

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = SiteSetting::instance();
        $settings->update([
            'show_language_filter' => $data['show_language_filter'],
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
