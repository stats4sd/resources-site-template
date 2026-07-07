<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('role')
                            ->label('Role')
                            ->options(UserRole::options())
                            ->required()
                            // Role lives in spatie's pivot, not on the users row, so it's
                            // hydrated/persisted by the page hooks — never as a column.
                            ->dehydrated(false)
                            ->helperText('Viewers get read-only access; editors manage content; admins also manage users and settings.')
                            ->rule(fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                                // Block demoting the last admin (mirrors UserPolicy::delete()).
                                if ($record && $record->isLastAdmin() && $value !== UserRole::Admin->value) {
                                    $fail('This is the last administrator and cannot be demoted.');
                                }
                            }),

                        // On create, the admin either sets a password now or emails the user a
                        // link to set their own (see CreateUser::afterCreate + SetPasswordMail).
                        // dehydrated(false): the choice lives in form state and is read by the page.
                        Radio::make('password_method')
                            ->label('Password')
                            ->options([
                                'email_link' => 'Email the user a link to set their own password',
                                'manual' => 'Set a password now',
                            ])
                            ->default('email_link')
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'create'),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            // Shown on edit (blank keeps current password) and on create only when
                            // "set a password now" is chosen; required only in that create case.
                            ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('password_method') === 'manual')
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $get('password_method') === 'manual')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->same('passwordConfirmation')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null)
                            ->maxLength(255),

                        TextInput::make('passwordConfirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('password_method') === 'manual')
                            ->dehydrated(false)
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $get('password_method') === 'manual')
                            ->requiredWith('password')
                            ->maxLength(255),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
                    ->placeholder('No role'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
