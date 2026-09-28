<?php

namespace App\Filament\Resources\InstructorResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('idempotency_key')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => \App\Support\Money::egp((int) $state)),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'succeeded' => 'success',
                    'failed' => 'danger',
                    'unknown' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('provider_reference'),
                Tables\Columns\TextColumn::make('attempts'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
