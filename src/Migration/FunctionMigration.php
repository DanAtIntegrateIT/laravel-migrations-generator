<?php

namespace KitLoong\MigrationsGenerator\Migration;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Migration\Blueprint\DBUnpreparedBlueprint;
use KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType;
use KitLoong\MigrationsGenerator\Migration\Writer\MigrationWriter;
use KitLoong\MigrationsGenerator\Migration\Writer\SquashWriter;
use KitLoong\MigrationsGenerator\Schema\Models\FunctionStored;
use KitLoong\MigrationsGenerator\Schema\Models\Procedure;
use KitLoong\MigrationsGenerator\Setting;
use KitLoong\MigrationsGenerator\Support\MigrationNameHelper;

class FunctionMigration 
{
    /**
     * @var \KitLoong\MigrationsGenerator\Support\MigrationNameHelper
     */
    private $migrationNameHelper;

    /**
     * @var \KitLoong\MigrationsGenerator\Migration\Writer\MigrationWriter
     */
    private $migrationWriter;

    /**
     * @var \KitLoong\MigrationsGenerator\Setting
     */
    private $setting;

    /**
     * @var \KitLoong\MigrationsGenerator\Migration\Writer\SquashWriter
     */
    private $squashWriter;

    public function __construct(
        MigrationNameHelper $migrationNameHelper,
        MigrationWriter $migrationWriter,
        Setting $setting,
        SquashWriter $squashWriter
    ) {
        $this->migrationNameHelper = $migrationNameHelper;
        $this->migrationWriter     = $migrationWriter;
        $this->setting             = $setting;
        $this->squashWriter        = $squashWriter;
    }
  
    /**
     * Create stored procedure migration.
     *
     * @return string The migration file path.
     */
    public function write(FunctionStored $function): string
    {
        $up   = $this->up($function);
        $down = $this->down($function);

        $this->migrationWriter->writeTo(
            $path = $this->makeMigrationPath($function->getName()),
            $this->setting->getStubPath(),
            $this->makeMigrationClassName($function->getName()),
            new Collection([$up]),
            new Collection([$down]),
            MigrationFileType::FUNCTION()
        );

        return $path;
    }

    /**
     * Write stored procedure migration into temporary file.
     */
    public function writeToTemp(FunctionStored $function): void
    {
        $up   = $this->up($function);
        $down = $this->down($function);

        $this->squashWriter->writeToTemp(new Collection([$up]), new Collection([$down]));
    }

    /**
     * Generates `up` db statement for stored functions.
     */
    private function up(FunctionStored $function): DBUnpreparedBlueprint
    {
        return new DBUnpreparedBlueprint($function->getDefinition());
    }

    /**
     * Generates `down` db statement for stored functions.
     */
    private function down(FunctionStored $function): DBUnpreparedBlueprint
    {
        return new DBUnpreparedBlueprint($function->getDropDefinition());
    }

    /**
     * Makes class name for stored function migration.
     *
     * @param  string  $procedure  Stored function name.
     */
    private function makeMigrationClassName(string $function): string
    {
        return $this->migrationNameHelper->makeClassName(
            $this->setting->getFunctionFilename(),
            $function
        );
    }

    /**
     * Makes file path for stored function migration.
     *
     * @param  string  $procedure  Stored function name.
     */
    private function makeMigrationPath(string $function): string
    {
        return $this->migrationNameHelper->makeFilename(
            $this->setting->getFunctionFilename(),
            $this->setting->getDateForMigrationFilename(),
            $function
        );
    }
}
