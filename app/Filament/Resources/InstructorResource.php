<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstructorResource\Pages;
use App\Filament\Resources\InstructorResource\RelationManagers;
use App\Models\Instructor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstructorResource extends Resource
{
    protected static ?string $model = Instructor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $egp = fn ($state) => \App\Support\Money::egp((int) $state);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('earned')
                    ->state(fn (Instructor $r) => (int) $r->ledgerEntries()->where('type', 'earned')->sum('amount'))
                    ->formatStateUsing($egp),
                Tables\Columns\TextColumn::make('paid')
                    ->state(fn (Instructor $r) => (int) $r->payouts()->where('status', 'succeeded')->sum('amount'))
                    ->formatStateUsing($egp),
                Tables\Columns\TextColumn::make('in_flight')
                    ->label('In flight')
                    ->state(fn (Instructor $r) => (int) $r->payouts()->whereIn('status', ['pending', 'processing', 'unknown'])->sum('amount'))
                    ->formatStateUsing($egp),
                Tables\Columns\TextColumn::make('outstanding')
                    ->state(fn (Instructor $r) => (int) $r->ledgerEntries()->sum('amount'))
                    ->formatStateUsing($egp),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PayoutsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstructors::route('/'),
            'view' => Pages\ViewInstructor::route('/{record}'),
        ];
    }
}
