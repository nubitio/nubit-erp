<?php

namespace App\Maker;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

class Util
{
    /**
     * @return array<int, string>
     */
    public static function getIgnoredColumns(): array
    {
        return ['created_at', 'updated_at', 'created_by', 'updated_by', 'date_created', 'date_updated'];
    }

    public static function getIdColumn(Table $entity): Column
    {
        $columns = $entity->getColumns();
        $primaryKey = $entity->getPrimaryKey();
        foreach ($columns as $column) {
            if (!empty($primaryKey?->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                return $column;
            }
        }
        throw new \Exception('No primary key found');
    }

    public static function getType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'decimal', 'float' => 'float',
            'boolean' => 'bool',
            'date', 'datetime' => '\DateTimeInterface',
            default => 'string',
        };
    }

    public static function getColumnType(Column $column): string
    {
        return match ($column->getType()->getName()) {
            'integer' => 'int',
            'decimal', 'float' => 'float',
            'boolean' => 'bool',
            'date', 'datetime' => '\DateTimeInterface',
            default => 'string',
        };
    }

    public static function getEntityIdField(Table $entity): string
    {
        $columns = $entity->getColumns();
        $primaryKey = $entity->getPrimaryKey();
        $field = '';
        foreach ($columns as $column) {
            if (!empty($primaryKey?->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $field = '    public ?'.self::getType($column->getType()->getName()).' $'.$column->getName().";\n";
                break;
            }
        }

        return $field;
    }

    public static function getEntityIdParam(Table $entity): string
    {
        $columns = $entity->getColumns();
        $primaryKey = $entity->getPrimaryKey();
        $param = '';
        foreach ($columns as $column) {
            if (!empty($primaryKey?->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $param = self::getType($column->getType()->getName()).' $id';
                break;
            }
        }

        return $param;
    }

    public static function getCastType(string $type): string
    {
        return match ($type) {
            'int' => 'intval',
            'float' => 'floatval',
            'bool' => 'boolval',
            default => 'strval',
        };
    }

    public static function getViewFields(Table $entity, string $varName = 'fields'): string
    {
        $columns = $entity->getColumns();
        $foreignKeys = $entity->getForeignKeys();
        $primaryKey = $entity->getPrimaryKey();
        $fields = '';
        foreach ($columns as $column) {
            if (in_array($column->getName(), Util::getIgnoredColumns())) {
                continue;
            }
            $field = '    '.$varName.".push(new FieldBuilder()\n";
            $formType = match ($column->getType()->getName()) {
                'integer' => 'FieldType.NUMBER',
                'boolean' => 'FieldType.SWITCH',
                'date' => 'FieldType.DATE',
                default => 'FieldType.TEXT'
            };
            if (in_array($column, $foreignKeys)) {
                $formType = 'FieldType.SELECT';
            }
            if (!empty($primaryKey?->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $formType = 'FieldType.NONE';
                $field .= "        .isIdentity(true)\n";
                $field .= "        .visible(false)\n";
            }
            $field .= '        .type('.$formType.")\n";
            $field .= "        .name('".$column->getName()."')\n";
            $field .= "        .label('".ucfirst(str_replace('_', ' ', $column->getName()))."')\n";
            if ($column->getNotnull()) {
                $field .= "        .required(true)\n";
            }
            $field .= "        .build());\n";
            $fields .= $field;
        }

        return $fields;
    }
}
