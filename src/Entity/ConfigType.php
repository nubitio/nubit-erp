<?php

namespace App\Entity;

enum ConfigType: string
{
    case STRING = "string";
    case INT = "int";
    case FLOAT = "float";
    case BOOLEAN = "bool";
    case JSON = "json";
}
