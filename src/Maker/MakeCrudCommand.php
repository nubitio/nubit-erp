<?php

namespace App\Maker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:app:crud',
    description: 'Creates a new App CRUD',
)]
class MakeCrudCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this->setHelp('Crud generator for a given entity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Crud generator');
            $io->text('This will generate a crud for a given entity');
            $tables = $this->connection->getSchemaManager()->listTableNames();
            $tableName = $io->choice('Please select the table', $tables);
            $table = $this->connection->getSchemaManager()->listTableDetails($tableName);
            $columns = $table->getColumns();
            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column->getName();
            }
            $module = $io->ask('Module name');
            $sortColumn = $io->choice('Sort column', $columnNames);
            $ignoredColumns = ['created_at', 'updated_at', 'created_by', 'updated_by', 'date_created', 'date_updated'];
            $modulePath = dirname(__DIR__, 2) . '/src/' . $module;
            $tsPath = dirname(__DIR__, 2) . '/assets/pages/';
            $domainPath = $modulePath . '/Domain/';
            $infrastructurePath = $modulePath . '/Infrastructure/';
            $applicationPath = $modulePath . '/Application/';
            $htmlPath = dirname(__DIR__, 2) . '/templates/' . $this->toKebabCase($module) . '/';
            if (!is_dir($domainPath)) {
                mkdir($domainPath, 0777, true);
            }
            if (!is_dir($infrastructurePath)) {
                mkdir($infrastructurePath, 0777, true);
            }
            if (!is_dir($applicationPath)) {
                mkdir($applicationPath, 0777, true);
            }
            $this->makeController($applicationPath, $module, $table);
            $this->makeRepositoryInterface($domainPath, $module);
            $this->makeCreateUseCase($domainPath, $module);
            $this->makeUpdateUseCase($domainPath, $module);
            $this->makeDeleteUseCase($domainPath, $module);
            $this->makeGetAllUseCase($domainPath, $module);
            $this->makeModel($domainPath, $module, $table);
            $this->makeEntity($infrastructurePath, $module, $table);
            $this->makeRepository($infrastructurePath, $module, $table, $sortColumn);
            $confirm = $io->confirm('Do you want to generate the view files?');
            if ($confirm) {
                if (!is_dir($tsPath)) {
                    mkdir($tsPath, 0777, true);
                }
                if (!is_dir($htmlPath)) {
                    mkdir($htmlPath, 0777, true);
                }
                $this->makeView($tsPath, $module, $table, $ignoredColumns);
                $this->makeTemplate($htmlPath, $module);
            }
            $io->success('Success');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function makeController(string $path, string $module, Table $table): void
    {
        $primaryKey = $table->getPrimaryKey();
        $controllerPath = $path . $module . 'Controller' . '.php';
        $controllerTemplate = dirname(__FILE__) . '/skeleton/crud/controller.tpl';
        $controllerContent = file_get_contents($controllerTemplate);
        $controllerContent = str_replace('{{module}}', $module, $controllerContent);
        $controllerContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $controllerContent);
        $controllerContent = str_replace('{{kebabModule}}', $this->toKebabCase($module), $controllerContent);
        $controllerContent = str_replace('{{table_id}}', $primaryKey->getColumns()[0], $controllerContent);
        file_put_contents($controllerPath, $controllerContent);
    }

    private function makeRepositoryInterface(string $path, string $module): void
    {
        $repositoryInterfacePath = $path . $module . 'RepositoryInterface' . '.php';
        $repositoryInterfaceTemplate = dirname(__FILE__) . '/skeleton/crud/repository-interface.tpl';
        $repositoryInterfaceContent = file_get_contents($repositoryInterfaceTemplate);
        $repositoryInterfaceContent = str_replace('{{module}}', $module, $repositoryInterfaceContent);
        $repositoryInterfaceContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $repositoryInterfaceContent);
        file_put_contents($repositoryInterfacePath, $repositoryInterfaceContent);
    }

    private function makeCreateUseCase(string $path, string $module): void
    {
        $createUseCasePath = $path . $module . 'CreateUseCase' . '.php';
        $createUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/create.tpl';
        $createUseCaseContent = file_get_contents($createUseCaseTemplate);
        $createUseCaseContent = str_replace('{{module}}', $module, $createUseCaseContent);
        $createUseCaseContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $createUseCaseContent);
        file_put_contents($createUseCasePath, $createUseCaseContent);
    }

    private function makeUpdateUseCase(string $path, string $module): void
    {
        $updateUseCasePath = $path . $module . 'UpdateUseCase' . '.php';
        $updateUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/update.tpl';
        $updateUseCaseContent = file_get_contents($updateUseCaseTemplate);
        $updateUseCaseContent = str_replace('{{module}}', $module, $updateUseCaseContent);
        $updateUseCaseContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $updateUseCaseContent);
        file_put_contents($updateUseCasePath, $updateUseCaseContent);
    }

    private function makeDeleteUseCase(string $path, string $module): void
    {
        $deleteUseCasePath = $path . $module . 'DeleteUseCase' . '.php';
        $deleteUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/delete.tpl';
        $deleteUseCaseContent = file_get_contents($deleteUseCaseTemplate);
        $deleteUseCaseContent = str_replace('{{module}}', $module, $deleteUseCaseContent);
        file_put_contents($deleteUseCasePath, $deleteUseCaseContent);
    }

    private function makeGetAllUseCase(string $path, string $module): void
    {
        $getAllUseCasePath = $path . $module . 'GetAllUseCase' . '.php';
        $getAllUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/get-all.tpl';
        $getAllUseCaseContent = file_get_contents($getAllUseCaseTemplate);
        $getAllUseCaseContent = str_replace('{{module}}', $module, $getAllUseCaseContent);
        file_put_contents($getAllUseCasePath, $getAllUseCaseContent);
    }

    private function makeModel(string $path, string $module, Table $table): void
    {
        $columns = $table->getColumns();
        $modelPath = $path . $module . '.php';
        $modelTemplate = dirname(__FILE__) . '/skeleton/crud/model.tpl';
        $modelContent = file_get_contents($modelTemplate);
        $modelContent = str_replace('{{module}}', $module, $modelContent);
        $modelFields = "";
        foreach ($columns as $column) {
            $modelFields .= "        #[SerializedName('" . $column->getName() . "')]\n";
            $modelFields .= "        public ?" . $this->getType($column->getType()->getName()) . " $" . $column->getName() . " = null,\n";
        }
        $modelContent = str_replace('{{fields}}', $modelFields, $modelContent);
        file_put_contents($modelPath, $modelContent);
    }

    private function makeEntity(string $path, string $module, Table $table): void
    {
        $columns = $table->getColumns();
        $primaryKey = $table->getPrimaryKey();
        $entityPath = $path . $module . 'Entity' . '.php';
        $entityTemplate = dirname(__FILE__) . '/skeleton/crud/entity.tpl';
        $entityContent = file_get_contents($entityTemplate);
        $entityContent = str_replace('{{module}}', $module, $entityContent);
        $entityContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $entityContent);
        $entityContent = str_replace('{{table}}', $table->getName(), $entityContent);
        $entityFields = "";
        foreach ($columns as $column) {
            if (!empty($primaryKey->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $entityFields .= "        #[Id]\n";
                $entityFields .= "        #[GeneratedValue]\n";
            }
            $entityFields .= "        #[Column(name: \"" . $column->getName() . "\", type: \"" . $column->getType()->getName() . "\")]\n";
            $entityFields .= "        public ?" . $this->getType($column->getType()->getName()) . " $" . $column->getName() . " = null,\n";
        }
        $entityContent = str_replace('{{fields}}', $entityFields, $entityContent);
        file_put_contents($entityPath, $entityContent);
    }

    private function makeRepository(string $path, string $module, Table $table, string $sortColumn): void
    {
        $primaryKey = $table->getPrimaryKey();
        $repositoryPath = $path . $module . 'Repository' . '.php';
        $repositoryTemplate = dirname(__FILE__) . '/skeleton/crud/repository.tpl';
        $repositoryContent = file_get_contents($repositoryTemplate);
        $repositoryContent = str_replace('{{module}}', $module, $repositoryContent);
        $repositoryContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $repositoryContent);
        $repositoryContent = str_replace('{{table}}', $table->getName(), $repositoryContent);
        $repositoryContent = str_replace('{{table_id}}', $primaryKey->getColumns()[0], $repositoryContent);
        $repositoryContent = str_replace('{{sortColumn}}', $sortColumn, $repositoryContent);
        file_put_contents($repositoryPath, $repositoryContent);
    }

    private function makeView(string $path, string $module, Table $table, array $ignoredColumns): void
    {
        $columns = $table->getColumns();
        $primaryKey = $table->getPrimaryKey();
        $foreignKeys = $table->getForeignKeys();
        $viewPath = $path . $module . '.ts';
        $viewTemplate = dirname(__FILE__) . '/skeleton/crud/view.tpl';
        $viewContent = file_get_contents($viewTemplate);
        $viewContent = str_replace('{{module}}', $module, $viewContent);
        $viewContent = str_replace('{{kebabModule}}', $this->toKebabCase($module), $viewContent);
        $viewFields = "";
        foreach ($columns as $column) {
            if (in_array($column->getName(), $ignoredColumns)) {
                continue;
            }
            $field = "        this.fields.push(new FieldBuilder()\n";
            $formType = match ($column->getType()->getName()) {
                'integer' => "FormType.NUMBER",
                'boolean' => "FormType.SWITCH",
                'date' => "FormType.DATE",
                default => "FormType.TEXT"
            };
            if (in_array($column, $foreignKeys)) {
                $formType = "FormType.SELECT";
            }
            if (!empty($primaryKey->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $formType = "FormType.NONE";
                $field .= "            .isIdentity(true)\n";
                $field .= "            .visible(false)\n";
            }
            $field .= "            .type(" . $formType . ")\n";
            $field .= "            .name('" . $column->getName() . "')\n";
            $field .= "            .label('" . ucfirst(str_replace('_', ' ', $column->getName())) . "')\n";
            if ($column->getNotnull()) {
                $field .= "            .required(true)\n";
            }
            $field .= "            .build());\n";
            $viewFields .= $field;
        }
        $viewContent = str_replace('{{fields}}', $viewFields, $viewContent);
        file_put_contents($viewPath, $viewContent);
    }

    private function makeTemplate(string $path, string $module): void
    {
        $twigPath = $path . 'index.html.twig';
        $twigTemplate = dirname(__FILE__) . '/skeleton/crud/index.tpl';
        $twigContent = file_get_contents($twigTemplate);
        $twigContent = str_replace('{{module}}', $module, $twigContent);
        $twigContent = str_replace('{{camelModule}}', $this->toCamelCase($module), $twigContent);
        file_put_contents($twigPath, $twigContent);
    }

    private function getType($type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            'date', 'datetime' => '\DateTime',
            default => 'string',
        };
    }

    private function toCamelCase(string $str): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
        $str[0] = strtolower($str[0]);
        return $str;
    }

    private function toKebabCase(string $str): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $str));
    }
}