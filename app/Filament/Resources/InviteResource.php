<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\InviteResource\Pages;
use App\Mail\UserInviteMail;
use App\Models\Invite;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class InviteResource extends Resource
{
    protected static ?string $model = Invite::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail): void {
                                    if (User::where('email', $value)->exists()) {
                                        $fail('A user with this email address already exists.');

                                        return;
                                    }

                                    if (Invite::pending()->where('email', $value)->exists()) {
                                        $fail('A pending invitation for this email address already exists.');
                                    }
                                };
                            }),

                        Select::make('role')
                            ->label('Role')
                            ->options(UserRole::options())
                            ->default(UserRole::Editor->value)
                            ->required()
                            ->helperText('The role the invitee receives when they complete registration.'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('inviter.name')
                    ->label('Invited by')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Invite $record): string => "Issue a fresh link and email it to {$record->email}.")
                    ->action(function (Invite $record): void {
                        $record->refreshToken();
                        Mail::to($record->email)->send(new UserInviteMail($record));

                        Notification::make()
                            ->success()
                            ->title('Invitation resent')
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Revoke'),
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
            'index' => Pages\ListInvites::route('/'),
            'create' => Pages\CreateInvite::route('/create'),
        ];
    }
}
