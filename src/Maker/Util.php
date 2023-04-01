<?php

namespace App\Maker;

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

    public static function getEntityIdField(Table $entity): string
    {
        $columns = $entity->getColumns();
        $primaryKey = $entity->getPrimaryKey();
        $field = '';
        foreach ($columns as $column) {
            if (!empty($primaryKey->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $field = "    public ?" . self::getType($column->getType()->getName()) . " $" . $column->getName() . ";\n";
                break;
            }
        }
        return $field;
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
            $field = "        this." . $varName . ".push(new FieldBuilder()\n";
            $formType = match ($column->getType()->getName()) {
                'integer' => 'FormType.NUMBER',
                'boolean' => 'FormType.SWITCH',
                'date' => 'FormType.DATE',
                default => 'FormType.TEXT'
            };
            if (in_array($column, $foreignKeys)) {
                $formType = 'FormType.SELECT';
            }
            if (!empty($primaryKey->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $formType = 'FormType.NONE';
                $field .= "            .isIdentity(true)\n";
                $field .= "            .visible(false)\n";
            }
            $field .= '            .type(' . $formType . ")\n";
            $field .= "            .name('" . $column->getName() . "')\n";
            $field .= "            .label('" . ucfirst(str_replace('_', ' ', $column->getName())) . "')\n";
            if ($column->getNotnull()) {
                $field .= "            .required(true)\n";
            }
            $field .= "            .build());\n";
            $fields .= $field;
        }
        return $fields;
    }
}