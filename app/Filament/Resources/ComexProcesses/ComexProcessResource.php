<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses;

use App\Filament\Resources\ComexProcesses\Pages\CreateComexProcess;
use App\Filament\Resources\ComexProcesses\Pages\EditComexProcess;
use App\Filament\Resources\ComexProcesses\Pages\ListComexProcesses;
use App\Filament\Resources\ComexProcesses\Pages\ViewComexProcess;
use App\Filament\Resources\ComexProcesses\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ComexProcesses\Schemas\ComexProcessForm;
use App\Filament\Resources\ComexProcesses\Schemas\ComexProcessInfolist;
use App\Filament\Resources\ComexProcesses\Tables\ComexProcessesTable;
use App\Models\ComexProcess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ComexProcessResource extends Resource
{
    protected static ?string $model = ComexProcess::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?string $modelLabel = 'Processo';

    protected static ?string $pluralModelLabel = 'Processos';

    protected static ?string $slug = 'processes';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ComexProcessForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ComexProcessInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComexProcessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            RelationManagers\MessagesRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComexProcesses::route('/'),
            'create' => CreateComexProcess::route('/create'),
            'view' => ViewComexProcess::route('/{record}'),
            'edit' => EditComexProcess::route('/{record}/edit'),
        ];
    }
}
