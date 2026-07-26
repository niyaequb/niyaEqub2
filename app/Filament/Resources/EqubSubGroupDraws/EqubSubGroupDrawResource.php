<?php

namespace App\Filament\Resources\EqubSubGroupDraws;

use App\Filament\Resources\EqubSubGroupDraws\Pages;
use App\Models\EqubSubGroup;
use App\Models\EqubSubGroupDraw;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EqubSubGroupDrawResource extends Resource
{
    protected static ?string $model = EqubSubGroupDraw::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function getNavigationLabel(): string
    {
        return 'Sub Group Draws';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.nav.equb');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Draw Configuration')->schema([
                    Radio::make('draw_type')
                        ->label('How would you like to select the winners?')
                        ->options([
                            'random' => 'Random Target Draw (Picks random groups until member target is met)',
                            'manual' => 'Manual Selection (Handpick winning groups)',
                        ])
                        ->live()
                        ->required()
                        ->default('random'),

                    TextInput::make('target_members')
                        ->label('Target Member Count')
                        ->helperText('System will randomly select Sub Groups until their combined member count hits or slightly exceeds this number.')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get) => $get('draw_type') === 'random')
                        ->visible(fn (Get $get) => $get('draw_type') === 'random'),

                    Select::make('manual_winners')
                        ->label('Select Winning Sub Groups')
                        ->multiple()
                        ->searchable()
                        ->options(EqubSubGroup::where('has_won', false)->pluck('name', 'id'))
                        ->required(fn (Get $get) => $get('draw_type') === 'manual')
                        ->visible(fn (Get $get) => $get('draw_type') === 'manual')
                        ->dehydrated(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('draw_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'random' => 'success',
                        'manual' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('target_members')
                    ->label('Target Goal')
                    ->placeholder('N/A')
                    ->numeric(),

                TextColumn::make('winners_count')
                    ->counts('winners')
                    ->label('Groups Won')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_winning_members')
                    ->label('Members Won')
                    ->getStateUsing(function (EqubSubGroupDraw $record) {
                        return $record->winners->sum(fn ($subgroup) => $subgroup->members()->count());
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('executedBy.name')
                    ->label('Executed By'),

                TextColumn::make('draw_date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEqubSubGroupDraws::route('/'),
            'create' => Pages\CreateEqubSubGroupDraw::route('/create'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-draws.index') ?? true);
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-draws.create') ?? true);
    }
}