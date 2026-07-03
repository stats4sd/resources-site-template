<?php

namespace App\Filament\Pages;

use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\SiteContent;
use Filament\Actions\Action as PageAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteContentPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.site-content';

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $title = 'Site Content';

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    public array $data = [];

    public function mount(): void
    {
        $contents = SiteContent::all()->keyBy('key');
        $formData = [];
        foreach (static::contentKeys() as $key) {
            $record = $contents->get($key);
            $formData[$key] = $record ? $record->getTranslations('value') : [];
        }
        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Home Page')
                    ->collapsible()
                    ->collapsed()
                    ->headerActions([
                        FormAction::make('view_home')
                            ->label('Open page')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url('/home')
                            ->openUrlInNewTab()
                            ->color('gray'),
                    ])
                    ->schema([
                        TranslatableComboField::make('home_heading_line1')
                            ->label('Main heading - first line')
                            ->description('Displayed in the brand colour.')
                            ->icon('heroicon-s-home')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\TextInput::class),
                        TranslatableComboField::make('home_heading_line2')
                            ->label('Main heading - second line')
                            ->description('Displayed in the main text colour.')
                            ->icon('heroicon-s-home')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\TextInput::class),
                        TranslatableComboField::make('home_intro')
                            ->label('Introduction paragraph')
                            ->description('Appears below the heading on the home page.')
                            ->icon('heroicon-s-home')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\Textarea::class),
                    ]),
                Forms\Components\Section::make('Library Page')
                    ->collapsible()
                    ->collapsed()
                    ->headerActions([
                        FormAction::make('view_library')
                            ->label('Open page')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url('/browse-all')
                            ->openUrlInNewTab()
                            ->color('gray'),
                    ])
                    ->schema([
                        TranslatableComboField::make('library_heading_line1')
                            ->label('Main heading - first line')
                            ->description('Displayed in white over the banner image.')
                            ->icon('heroicon-s-book-open')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\TextInput::class),
                        TranslatableComboField::make('library_heading_line2')
                            ->label('Main heading - second line')
                            ->description('Displayed in white over the banner image.')
                            ->icon('heroicon-s-book-open')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\TextInput::class),
                        TranslatableComboField::make('library_hero_description')
                            ->label('Introductory text')
                            ->description('Appears below the heading at the top of the Browse Library page.')
                            ->icon('heroicon-s-book-open')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\Textarea::class),
                    ]),
                Forms\Components\Section::make('Footer')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TranslatableComboField::make('footer_admin_login_label')
                            ->label('Staff login button text')
                            ->description('Appears as the button label in the site footer, linking to the admin panel.')
                            ->icon('fluentui-document-footer-20-o')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->childField(Forms\Components\TextInput::class),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            SiteContent::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Notification::make()->success()->title('Saved successfully')->send();
    }

    protected function getFormActions(): array
    {
        return [
            PageAction::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    private static function contentKeys(): array
    {
        return ['home_heading_line1', 'home_heading_line2', 'home_intro', 'library_heading_line1', 'library_heading_line2', 'library_hero_description', 'footer_admin_login_label'];
    }
}
