<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

trait RemovesTwoFactorAuthentication
{
    protected function removeTwoFactorTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('remove_2fa')
            ->label('Remove 2FA')
            ->icon('heroicon-o-lock-open')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove two-factor authentication')
            ->modalDescription(fn (User $record): string => "This will disable 2FA for {$record->name}. They will be able to sign in with their password only and must set up 2FA again.")
            ->modalSubmitActionLabel('Remove 2FA')
            ->visible(fn (User $record): bool => $this->actorCanRemoveTwoFactor($record))
            ->action(function (User $record): void {
                abort_unless($this->actorCanRemoveTwoFactor($record), 403, 'Unauthorized action.');

                $record->removeTwoFactorAuthentication();

                Notification::make()
                    ->title('Two-factor authentication removed')
                    ->body("2FA has been removed for {$record->name}.")
                    ->success()
                    ->send();
            });
    }

    protected function actorCanRemoveTwoFactor(User $record): bool
    {
        $actor = Auth::user();

        if (! $actor || ! $record->hasTwoFactorEnabled()) {
            return false;
        }

        if ((int) $actor->business_id === 1) {
            return true;
        }

        return (int) $actor->business_id === (int) $record->business_id
            && (int) $record->business_id !== 1;
    }
}
