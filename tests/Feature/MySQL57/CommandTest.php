<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\MySQL57;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Schema\Models\ForeignKey;
use KitLoong\MigrationsGenerator\Schema\Models\Index;
use KitLoong\MigrationsGenerator\Schema\MySQLSchema;
use KitLoong\MigrationsGenerator\Support\CheckMigrationMethod;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CommandTest extends MySQL57TestCase
{
    use CheckMigrationMethod;

    public function testRun()
    {
        $migrateTemplates = function () {
            $this->migrateGeneral('mysql57');
        };

        $generateMigrations = function () {
            $this->generateMigrations();
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testDown()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations();

        $this->rollbackMigrationsFrom('mysql57', $this->storageMigrations());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertCount(1, $tables);
        $this->assertCount(0, $views);
        $this->assertSame(0, DB::table('migrations')->count());
    }

    public function testCollation()
    {
        $migrateTemplates = function () {
            $this->migrateCollation('mysql57');
        };

        $generateMigrations = function () {
            $this->generateMigrations(['--use-db-collation' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testSquashUp()
    {
        $migrateTemplates = function () {
            $this->migrateGeneral('mysql57');
        };

        $generateMigrations = function () {
            $this->generateMigrations(['--squash' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testSquashDown()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations(['--squash' => true]);

        $this->rollbackMigrationsFrom('mysql57', $this->storageMigrations());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertCount(1, $tables);
        $this->assertCount(0, $views);
        $this->assertSame(0, DB::table('migrations')->count());
    }

    public function testTables()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations([
            '--tables' => implode(',', [
                'all_columns_mysql57',
                'users_mysql57',
                'users_mysql57_view'
            ])
        ]);

        $this->dropAllTables();

        $this->runMigrationsFrom('mysql57', $this->storageMigrations());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertCount(3, $tables);
        $this->assertCount(1, $views);

        $this->assertContains('all_columns_mysql57', $tables);
        $this->assertContains('migrations', $tables);
        $this->assertContains('users_mysql57', $tables);
        $this->assertContains('users_mysql57_view', $views);
    }

    public function testIgnore()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $allAssets = count($this->getTableNames()) + count($this->getViewNames());

        $ignores = [
            'quoted-name-foreign-mysql57',
            'increments_mysql57',
            'timestamps_mysql57',
            'users_mysql57_view',
        ];

        $ignoreNotExists = [
            'not_exists',
        ];

        $this->generateMigrations([
            '--ignore' => implode(',', $ignores + $ignoreNotExists)
        ]);

        $this->dropAllTables();

        $this->runMigrationsFrom('mysql57', $this->storageMigrations());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertSame(count($tables) + count($views), $allAssets - count($ignores));
        $this->assertEmpty(array_intersect($ignores, $tables));
    }

    public function testDefaultIndexNames()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations([
            '--tables'              => 'test_index_mysql57',
            '--default-index-names' => true
        ]);

        $this->dropAllTables();

        $this->runMigrationsFrom('mysql57', $this->storageMigrations());

        $indexes = app(MySQLSchema::class)
            ->getTable('test_index_mysql57')
            ->getIndexes();

        $actualIndexes = $indexes->map(function (Index $index) {
            return $index->getName();
        })->toArray();

        $expectedIndexes = [
            'PRIMARY',
            'test_index_mysql57_chain_index',
            'test_index_mysql57_chain_unique',
            'test_index_mysql57_col_multi1_col_multi2_index',
            'test_index_mysql57_col_multi1_col_multi2_unique',
            'test_index_mysql57_col_multi_custom1_col_multi_custom2_index',
            'test_index_mysql57_col_multi_custom1_col_multi_custom2_unique',
            'test_index_mysql57_column_hyphen_index',
            'test_index_mysql57_index_custom_index',
            'test_index_mysql57_index_index',
            'test_index_mysql57_spatial_index_custom_spatialindex',
            'test_index_mysql57_spatial_index_spatialindex',
            'test_index_mysql57_unique_custom_unique',
            'test_index_mysql57_unique_unique',
        ];

        if ($this->hasFullText()) {
            $expectedIndexes = array_merge($expectedIndexes, [
                'test_index_mysql57_chain_fulltext',
                'test_index_mysql57_col_multi1_col_multi2_fulltext',
                'test_index_mysql57_fulltext_custom_fulltext',
                'test_index_mysql57_fulltext_fulltext',
            ]);
        }

        sort($actualIndexes);
        sort($expectedIndexes);

        $this->assertSame(
            $expectedIndexes,
            $actualIndexes
        );
    }

    public function testDefaultFKNames()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations(['--default-fk-names' => true]);

        $this->dropAllTables();

        $this->runMigrationsFrom('mysql57', $this->storageMigrations());

        $foreignKeys     = app(MySQLSchema::class)->getTableForeignKeys('user_profile_mysql57');
        $foreignKeyNames = $foreignKeys->map(function (ForeignKey $foreignKey) {
            return $foreignKey->getName();
        })
            ->sort()
            ->values()
            ->toArray();

        $this->assertSame(
            [
                'user_profile_mysql57_user_id_fk_constraint_foreign',
                'user_profile_mysql57_user_id_fk_custom_foreign',
                'user_profile_mysql57_user_id_foreign',
                'user_profile_mysql57_user_id_user_sub_id_fk_custom_foreign',
                'user_profile_mysql57_user_id_user_sub_id_foreign',
            ],
            $foreignKeyNames
        );

        $this->rollbackMigrationsFrom('mysql57', $this->storageMigrations());
    }

    public function testDate()
    {
        $migrateTemplates = function () {
            $this->migrateGeneral('mysql57');
        };

        $generateMigrations = function () {
            $this->generateMigrations([
                '--date' => '2021-10-08 09:30:40',
            ]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testTableFilenameAndViewFilename()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations([
            '--table-filename' => '[datetime_prefix]_custom_[table]_table.php',
            '--view-filename'  => '[datetime_prefix]_custom_[table]_view.php',
        ]);

        $migrations = [];
        foreach (File::files($this->storageMigrations()) as $migration) {
            $migrations[] = substr($migration->getFilenameWithoutExtension(), 18);
        }

        $this->assertTrue(in_array('custom_all_columns_mysql57_table', $migrations));
        $this->assertTrue(in_array('custom_users_mysql57_view_view', $migrations));
    }

    public function testFKFilename()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations(['--fk-filename' => '[datetime_prefix]_custom_[table]_table.php']);

        $migrations = [];
        foreach (File::files($this->storageMigrations()) as $migration) {
            $migrations[] = substr($migration->getFilenameWithoutExtension(), 18);
        }

        $this->assertSame('custom_user_profile_mysql57_table', $migrations[count($migrations) - 1]);
    }

    public function testSkipView()
    {
        $this->migrateGeneral('mysql57');

        $this->truncateMigration();

        $this->generateMigrations([
            '--skip-views' => true,
        ]);

        $migrations = [];
        foreach (File::files($this->storageMigrations()) as $migration) {
            $migrations[] = substr($migration->getFilenameWithoutExtension(), 18);
        }

        $this->assertTrue(in_array('create_all_columns_mysql57_table', $migrations));
        $this->assertFalse(in_array('create_users_mysql57_view_view', $migrations));
    }

    public function testWillCreateMigrationTable()
    {
        $this->migrateGeneral('mysql57');
        Schema::dropIfExists('migrations');

        $this->generateMigrations();

        $this->assertTrue(Schema::hasTable('migrations'));
    }

    private function verify(callable $migrateTemplates, callable $generateMigrations)
    {
        $migrateTemplates();

        $this->truncateMigration();
        $this->dumpSchemaAs($this->storageSql('expected.sql'));

        $generateMigrations();

        $this->assertMigrations();

        $this->dropAllTables();

        $this->runMigrationsFrom('mysql57', $this->storageMigrations());

        $this->truncateMigration();
        $this->dumpSchemaAs($this->storageSql('actual.sql'));

        $this->assertFileEqualsIgnoringOrder(
            $this->storageSql('expected.sql'),
            $this->storageSql('actual.sql')
        );
    }
}
