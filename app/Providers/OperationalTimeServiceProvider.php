<?php

namespace App\Providers;

use App\Support\SharedTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class OperationalTimeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerMacros();
        $this->registerFilamentTimezones();
        $this->registerBlade();
    }

    private function registerMacros(): void
    {
        $inOperationalTimezone = function (?object $record = null) {
            return $this->copy()->timezone(SharedTime::timezoneForRecord($record));
        };

        IlluminateCarbon::macro('inOperationalTimezone', $inOperationalTimezone);
        Carbon::macro('inOperationalTimezone', $inOperationalTimezone);
        CarbonImmutable::macro('inOperationalTimezone', $inOperationalTimezone);

        $whereOperationalDay = function (string $column, ?string $localDate = null) {
            return SharedTime::constrainToOperationalDay($this, $column, $localDate);
        };

        QueryBuilder::macro('whereOperationalDay', $whereOperationalDay);
        EloquentBuilder::macro('whereOperationalDay', $whereOperationalDay);

        $whereOperationalPeriod = function (string $column, string $period = 'today') {
            return SharedTime::constrainToOperationalPeriod($this, $column, $period);
        };

        QueryBuilder::macro('whereOperationalPeriod', $whereOperationalPeriod);
        EloquentBuilder::macro('whereOperationalPeriod', $whereOperationalPeriod);
    }

    private function registerFilamentTimezones(): void
    {
        TextColumn::configureUsing(function (TextColumn $column): void {
            $column->timezone(fn (): string => SharedTime::timezoneForRecord($column->getRecord()));
        });

        TextEntry::configureUsing(function (TextEntry $entry): void {
            $entry->timezone(fn (): string => SharedTime::timezoneForRecord($entry->getRecord()));
        });

        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            if ($component instanceof DatePicker || ! $component->hasTime()) {
                return;
            }

            $component->timezone(fn (): string => SharedTime::displayTimezone());
        });
    }

    private function registerBlade(): void
    {
        Blade::directive('localtime', function (string $expression): string {
            return "<?php echo e(\\App\\Support\\SharedTime::formatLocal({$expression})); ?>";
        });
    }
}
