<?php

namespace KitLoong\MigrationsGenerator\Tests;

use Illuminate\Support\Facades\DB;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\SchemaBuilder;
use KitLoong\MigrationsGenerator\Migration\Blueprint\SchemaBlueprint;
use KitLoong\MigrationsGenerator\Migration\Blueprint\TableBlueprint;
use KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType;
use KitLoong\MigrationsGenerator\Migration\Writer\MigrationWriter;
use KitLoong\MigrationsGenerator\Setting;
use KitLoong\MigrationsGenerator\Support\TableName;
use Mockery\MockInterface;

class MigrationWriterTest extends TestCase
{
    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function testWrite()
    {
        $setting = app(Setting::class);
        $setting->setDefaultConnection(DB::getDefaultConnection());

        $this->mock(TableName::class, function (MockInterface $mock) {
            $mock->shouldReceive('stripPrefix')
                ->andReturn('test');
        });

        $up        = new SchemaBlueprint('mysql', 'users', SchemaBuilder::CREATE());
        $blueprint = new TableBlueprint();
        $blueprint->setProperty('collation', 'utf-8');
        $blueprint->setProperty('something', 1);
        $blueprint->setProperty('something', true);
        $blueprint->setProperty('something', false);
        $blueprint->setProperty('something', null);
        $blueprint->setProperty('something', [1, 2, 3, 'abc', null, true, false, ['a', 2, 'c']]);
        $blueprint->setLineBreak();
        $blueprint->setMethodByName('string', 'name', 100)
            ->chain('comment', 'Hello')
            ->chain('default', 'Test');
        $up->setBlueprint($blueprint);

        $down = new SchemaBlueprint('mysql', 'users', SchemaBuilder::DROP_IF_EXISTS());

        $migration = app(MigrationWriter::class);
        $migration->writeTo(
            storage_path('migration.php'),
            config('generators.config.migration_template_path'),
            'Tester',
            $up,
            $down,
            MigrationFileType::TABLE()
        );

        $this->assertFileExists(storage_path('migration.php'));
    }
}
