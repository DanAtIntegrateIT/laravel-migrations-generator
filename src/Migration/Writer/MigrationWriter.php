<?php

namespace KitLoong\MigrationsGenerator\Migration\Writer;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint;
use KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType;
use KitLoong\MigrationsGenerator\Migration\Enum\Space;

class MigrationWriter
{
    private $migrationStub;

    public function __construct(MigrationStub $migrationStub)
    {
        $this->migrationStub = $migrationStub;
    }

    /**
     * Writes migration to destination.
     *
     * @param  string  $path  Migration file destination path.
     * @param  string  $stubPath  Migration stub file path.
     * @param  string  $className
     * @param  \Illuminate\Support\Collection<\KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint>  $up  Blueprint of migration `up`.
     * @param  \Illuminate\Support\Collection<\KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint>  $down  Blueprint of migration `down`.
     * @param  \KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType  $migrationFileType
     */
    public function writeTo(
        string $path,
        string $stubPath,
        string $className,
        Collection $up,
        Collection $down,
        MigrationFileType $migrationFileType
    ): void {
        try {
            $stub = $this->migrationStub->getStub($stubPath);

            $useDBFacade = false;

            // TODO DRY
            $upString = $up->map(function (WritableBlueprint $up) {
                return $up->toString();
            })->implode(Space::LINE_BREAK() . Space::TAB() . Space::TAB()); // Add tab to prettify

            if (Str::contains($upString, 'DB::')) {
                $useDBFacade = true;
            }

            $downString = $down->map(function (WritableBlueprint $down) {
                return $down->toString();
            })->implode(Space::LINE_BREAK() . Space::TAB() . Space::TAB()); // Add tab to prettify

            if (Str::contains($downString, 'DB::')) {
                $useDBFacade = true;
            }

            $use = implode(Space::LINE_BREAK(), $this->getImports($migrationFileType, $useDBFacade));

            File::put(
                $path,
                $this->migrationStub->populateStub($stub, $use, $className, $upString, $downString)
            );
        } catch (FileNotFoundException $e) {
            // Do nothing.
        }
    }

    /**
     * @param  \KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType  $migrationFileType
     * @param  bool  $useDBFacade
     * @return string[]
     */
    private function getImports(MigrationFileType $migrationFileType, bool $useDBFacade): array
    {
        if (
            $migrationFileType->equals(MigrationFileType::VIEW()) ||
            $migrationFileType->equals(MigrationFileType::PROCEDURE())
        ) {
            return [
                'use Illuminate\Database\Migrations\Migration;',
                'use Illuminate\Support\Facades\DB;',
            ];
        }

        $imports = [
            'use Illuminate\Database\Migrations\Migration;',
            'use Illuminate\Database\Schema\Blueprint;',
        ];

        if ($useDBFacade) {
            $imports[] = 'use Illuminate\Support\Facades\DB;';
        }

        // Push at the last to maintain alphabetically sort.
        $imports[] = 'use Illuminate\Support\Facades\Schema;';

        return $imports;
    }
}
