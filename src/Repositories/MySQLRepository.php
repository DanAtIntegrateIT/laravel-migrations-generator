<?php

namespace KitLoong\MigrationsGenerator\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Repositories\Entities\MySQL\ShowColumn;
use KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition;
use KitLoong\MigrationsGenerator\Repositories\Entities\FunctionDefinition;
use KitLoong\MigrationsGenerator\Setting;

class MySQLRepository extends Repository
{
    /**
     * Show column by table and column name.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     */
    public function showColumn(string $table, string $column): ?ShowColumn
    {
        $result = DB::selectOne("SHOW COLUMNS FROM `$table` WHERE Field = '$column'");
        return $result === null ? null : new ShowColumn($result);
    }

    /**
     * Get enum values.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getEnumPresetValues(string $table, string $column): Collection
    {
        $result = DB::selectOne("SHOW COLUMNS FROM `$table` WHERE Field = '$column' AND Type LIKE 'enum(%'");

        if ($result === null) {
            return new Collection();
        }

        $showColumn = new ShowColumn($result);
        $value      = substr(
            str_replace('enum(\'', '', $showColumn->getType()),
            0,
            -2
        );
        return new Collection(explode("','", $value));
    }

    /**
     * Get set values.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getSetPresetValues(string $table, string $column): Collection
    {
        $result = DB::selectOne("SHOW COLUMNS FROM `$table` WHERE Field = '$column' AND Type LIKE 'set(%'");

        if ($result === null) {
            return new Collection();
        }

        $showColumn = new ShowColumn($result);
        $value      = substr(
            str_replace('set(\'', '', $showColumn->getType()),
            0,
            -2
        );
        return new Collection(explode("','", $value));
    }

    /**
     * Checks if column has `on update CURRENT_TIMESTAMP`
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     */
    public function isOnUpdateCurrentTimestamp(string $table, string $column): bool
    {
        // MySQL5.7 shows "on update CURRENT_TIMESTAMP"
        // MySQL8 shows "DEFAULT_GENERATED on update CURRENT_TIMESTAMP"
        $result = DB::selectOne(
            "SHOW COLUMNS FROM `$table`
                WHERE Field = '$column'
                    AND Type = 'timestamp'
                    AND Extra LIKE '%on update CURRENT_TIMESTAMP%'"
        );
        return !($result === null);
    }

    /**
     * Get the virtual column definition by table and column name.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     * @return string|null  The virtual column definition. NULL if not found.
     */
    public function getVirtualDefinition(string $table, string $column): ?string
    {
        return $this->getGenerationExpression($table, $column, 'VIRTUAL GENERATED');
    }

    /**
     * Get the stored column definition by table and column name.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     * @return string|null  The stored column definition. NULL if not found.
     */
    public function getStoredDefinition(string $table, string $column): ?string
    {
        return $this->getGenerationExpression($table, $column, 'STORED GENERATED');
    }

    /**
     * Get a list of stored procedures.
     *
     * @return \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition>
     */
    public function getProcedures(): Collection
    {
        $list       = new Collection();
        $procedures = DB::select("SHOW PROCEDURE STATUS WHERE Db='" . DB::getDatabaseName() . "'");
        $setting = app(Setting::class);

        foreach ($procedures as $procedure) {
            // Change all keys to lowercase.
            $procedureArr = array_change_key_case((array) $procedure);
            $createProc   = $this->getProcedure($procedureArr['name']);

            if ($setting->isNoDefiner()){
                $pattern = "/CREATE DEFINER=`[^`]+`@`[^`]+` PROCEDURE/";
                $createProc->{'Create Procedure'} = preg_replace($pattern, "CREATE PROCEDURE", $createProc->{'Create Procedure'});
            }

            // Change all keys to lowercase.
            $createProcArr = array_change_key_case((array) $createProc);
            $list->push(new ProcedureDefinition($procedureArr['name'], $createProcArr['create procedure']));
        }

        return $list;
    }

    /**
     * Get single stored procedure by name.
     *
     * @param  string  $procedure  Procedure name.
     * @return mixed
     */
    private function getProcedure(string $procedure)
    {
        return DB::selectOne("SHOW CREATE PROCEDURE $procedure");
    }

    /**
     * Get a list of stored functions.
     *
     * @return \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition>
     */
    public function getFunctions(): Collection
    {
        $list       = new Collection();
        $functions = DB::select("SHOW FUNCTION STATUS WHERE Db='" . DB::getDatabaseName() . "'");
        $setting = app(Setting::class);

        foreach ($functions as $function) {
            // Change all keys to lowercase.
            $functionArr = array_change_key_case((array) $function);
            $createFunc   = $this->getFunction($functionArr['name']);

            if ($setting->isNoDefiner()){
                $pattern = "/CREATE DEFINER=`[^`]+`@`[^`]+` FUNCTION/";
                $createFunc->{'Create Function'} = preg_replace($pattern, "CREATE FUNCTION", $createFunc->{'Create Function'});
            }

            // Change all keys to lowercase.
            $createFuncArr = array_change_key_case((array) $createFunc);
            $list->push(new FunctionDefinition($functionArr['name'], $createFuncArr['create function']));
        }

        return $list;
    }

    /**
     * Get single stored function by name.
     *
     * @param  string  $function  Function name.
     * @return mixed
     */
    private function getFunction(string $function)
    {
        return DB::selectOne("SHOW CREATE FUNCTION $function");
    }

    /**
     * Get the column GENERATION_EXPRESSION when EXTRA is 'VIRTUAL GENERATED' or 'STORED GENERATED'.
     *
     * @param  'VIRTUAL GENERATED'|'STORED GENERATED'  $extra
     */
    private function getGenerationExpression(string $table, string $column, string $extra): ?string
    {
        try {
            $definition = DB::selectOne(
                "SELECT GENERATION_EXPRESSION
                FROM information_schema.COLUMNS
                WHERE TABLE_NAME = '$table'
                    AND COLUMN_NAME = '$column'
                    AND EXTRA = '$extra'"
            );
        } catch (QueryException $exception) {
            // Check if error caused by missing column 'GENERATION_EXPRESSION'.
            // The column is introduced since MySQL 5.7 and MariaDB 10.2.5.
            // @see https://mariadb.com/kb/en/information-schema-columns-table/
            // @see https://dev.mysql.com/doc/refman/5.7/en/information-schema-columns-table.html
            if (
                Str::contains(
                    $exception->getMessage(),
                    "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'GENERATION_EXPRESSION'",
                    true
                )
            ) {
                return null;
            }

            throw $exception;
        }

        if ($definition === null) {
            return null;
        }

        $definitionArr = array_change_key_case((array) $definition);
        return $definitionArr['generation_expression'] !== '' ? $definitionArr['generation_expression'] : null;
    }
}
