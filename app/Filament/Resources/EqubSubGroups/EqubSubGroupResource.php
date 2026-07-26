<?php

namespace App\Filament\Resources\EqubSubGroups;

use App\Filament\Resources\EqubSubGroups\Pages\CreateEqubSubGroup;
use App\Filament\Resources\EqubSubGroups\Pages\EditEqubSubGroup;
use App\Filament\Resources\EqubSubGroups\Pages\ListEqubSubGroups;
use App\Filament\Resources\EqubSubGroups\RelationManagers\MembersRelationManager;
use App\Filament\Resources\EqubSubGroups\Schemas\EqubSubGroupForm;
use App\Filament\Resources\EqubSubGroups\Tables\EqubSubGroupsTable;
use App\Models\EqubSubGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EqubSubGroupResource extends Resource
{
    protected static ?string $model = EqubSubGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Equb Sub Groups';
    }

    public static function getModelLabel(): string
    {
        return 'Equb Sub Group';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Equb Sub Groups';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Equb';
    }

    public static function form(Schema $schema): Schema
    {
        return EqubSubGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EqubSubGroupsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['equbGroup', 'inviter.user', 'members', 'payments']) 
            ->withCount('members'); 
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEqubSubGroups::route('/'),
            'create' => CreateEqubSubGroup::route('/create'),
            'edit' => EditEqubSubGroup::route('/{record}/edit'),
        ];
    }

    // --- Authorization ---

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-sub-groups.index') ?? true);
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-sub-groups.index') ?? true);
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-sub-groups.create') ?? true);
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-sub-groups.edit') ?? true);
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Super Admin') || Auth::user()->can('equb-sub-groups.delete') ?? true);
    }
}