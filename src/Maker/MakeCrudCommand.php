<?php

namespace App\Maker;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeCrudCommand extends Command
{
    protected static $defaultName = 'app:make-crud';
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
            $primaryKey = $table->getPrimaryKey();
            $foreignKeys = $table->getForeignKeys();
            $module = $io->ask('Module name');
            $sortColumn = $io->choice('Sort column', $columnNames);
            $ignoredColumns = ['created_at', 'updated_at', 'created_by', 'updated_by', 'date_created', 'date_updated'];
            $configPath = dirname(__DIR__, 2) . '/config/';
            $modulePath = dirname(__DIR__, 2) . '/src/' . $module;
            $tsPath = dirname(__DIR__, 2) . '/assets/pages/';
            $domainPath = $modulePath . '/Domain/';
            $infrastructurePath = $modulePath . '/Infrastructure/';
            $applicationPath = $modulePath . '/Application/';
            $htmlPath = dirname(__DIR__, 2) . '/templates/' . strtolower($module) . '/';
            if (!is_dir($tsPath)) {
                mkdir($tsPath, 0777, true);
            }
            if (!is_dir($domainPath)) {
                mkdir($domainPath, 0777, true);
            }
            if (!is_dir($infrastructurePath)) {
                mkdir($infrastructurePath, 0777, true);
            }
            if (!is_dir($applicationPath)) {
                mkdir($applicationPath, 0777, true);
            }
            if (!is_dir($htmlPath)) {
                mkdir($htmlPath, 0777, true);
            }
            $controllerPath = $applicationPath . $module . 'Controller' . '.php';
            $controllerTemplate = dirname(__FILE__) . '/templates/crud/controller.txt';
            $controllerContent = file_get_contents($controllerTemplate);
            $controllerContent = str_replace('{{module}}', $module, $controllerContent);
            $controllerContent = str_replace('{{l-module}}', strtolower($module), $controllerContent);
            $controllerContent = str_replace('{{table_id}}', $primaryKey->getColumns()[0], $controllerContent);
            file_put_contents($controllerPath, $controllerContent);
            $repositoryInterfacePath = $domainPath . $module . 'RepositoryInterface' . '.php';
            $repositoryInterfaceTemplate = dirname(__FILE__) . '/templates/crud/repository-interface.txt';
            $repositoryInterfaceContent = file_get_contents($repositoryInterfaceTemplate);
            $repositoryInterfaceContent = str_replace('{{module}}', $module, $repositoryInterfaceContent);
            $repositoryInterfaceContent = str_replace('{{l-module}}', strtolower($module), $repositoryInterfaceContent);
            file_put_contents($repositoryInterfacePath, $repositoryInterfaceContent);
            $repositoryPath = $infrastructurePath . $module . 'Repository' . '.php';
            $repositoryTemplate = dirname(__FILE__) . '/templates/crud/repository.txt';
            $repositoryContent = file_get_contents($repositoryTemplate);
            $repositoryContent = str_replace('{{module}}', $module, $repositoryContent);
            $repositoryContent = str_replace('{{l-module}}', strtolower($module), $repositoryContent);
            $repositoryContent = str_replace('{{table}}', $tableName, $repositoryContent);
            $repositoryContent = str_replace('{{table_id}}', $primaryKey->getColumns()[0], $repositoryContent);
            $repositoryContent = str_replace('{{sortColumn}}', $sortColumn, $repositoryContent);
            file_put_contents($repositoryPath, $repositoryContent);
            $createUseCasePath = $domainPath . $module . 'CreateUseCase' . '.php';
            $createUseCaseTemplate = dirname(__FILE__) . '/templates/crud/create.txt';
            $createUseCaseContent = file_get_contents($createUseCaseTemplate);
            $createUseCaseContent = str_replace('{{module}}', $module, $createUseCaseContent);
            $createUseCaseContent = str_replace('{{l-module}}', strtolower($module), $createUseCaseContent);
            file_put_contents($createUseCasePath, $createUseCaseContent);
            $updateUseCasePath = $domainPath . $module . 'UpdateUseCase' . '.php';
            $updateUseCaseTemplate = dirname(__FILE__) . '/templates/crud/update.txt';
            $updateUseCaseContent = file_get_contents($updateUseCaseTemplate);
            $updateUseCaseContent = str_replace('{{module}}', $module, $updateUseCaseContent);
            $updateUseCaseContent = str_replace('{{l-module}}', strtolower($module), $updateUseCaseContent);
            file_put_contents($updateUseCasePath, $updateUseCaseContent);
            $deleteUseCasePath = $domainPath . $module . 'DeleteUseCase' . '.php';
            $deleteUseCaseTemplate = dirname(__FILE__) . '/templates/crud/delete.txt';
            $deleteUseCaseContent = file_get_contents($deleteUseCaseTemplate);
            $deleteUseCaseContent = str_replace('{{module}}', $module, $deleteUseCaseContent);
            file_put_contents($deleteUseCasePath, $deleteUseCaseContent);
            $getAllUseCasePath = $domainPath . $module . 'GetAllUseCase' . '.php';
            $getAllUseCaseTemplate = dirname(__FILE__) . '/templates/crud/get-all.txt';
            $getAllUseCaseContent = file_get_contents($getAllUseCaseTemplate);
            $getAllUseCaseContent = str_replace('{{module}}', $module, $getAllUseCaseContent);
            file_put_contents($getAllUseCasePath, $getAllUseCaseContent);
            $modelPath = $domainPath . $module . '.php';
            $modelTemplate = dirname(__FILE__) . '/templates/crud/model.txt';
            $modelContent = file_get_contents($modelTemplate);
            $modelContent = str_replace('{{module}}', $module, $modelContent);
            $modelFields = "";
            foreach ($columns as $column) {
                $modelFields .= "        #[SerializedName('" . $column->getName() . "')]\n";
                $modelFields .= "        public ?" . $this->getType($column->getType()->getName()) . " $" . $column->getName() . ",\n";
            }
            $modelContent = str_replace('{{fields}}', $modelFields, $modelContent);
            file_put_contents($modelPath, $modelContent);
            $entityPath = $infrastructurePath . $module . 'Entity' . '.php';
            $entityTemplate = dirname(__FILE__) . '/templates/crud/entity.txt';
            $entityContent = file_get_contents($entityTemplate);
            $entityContent = str_replace('{{module}}', $module, $entityContent);
            $entityContent = str_replace('{{l-module}}', strtolower($module), $entityContent);
            $entityContent = str_replace('{{table}}', $tableName, $entityContent);
            $entityFields = "";
            $mapToDomainModelFields = "";
            $mapFromDomainModelFields = "";
            foreach ($columns as $column) {
                if (!empty($primaryKey->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                    $entityFields .= "    #[Id]\n";
                    $entityFields .= "    #[GeneratedValue]\n";
                }
                $entityFields .= "    #[Column(name: \"" . $column->getName() . "\", type: \"" . $column->getType()->getName() . "\")]\n";
                $entityFields .= "    private ?" . $this->getType($column->getType()->getName()) . " $" . $column->getName() . ";\n";
                $mapToDomainModelFields .= "            \$this->" . $column->getName() . ",\n";
                $mapFromDomainModelFields .= "        \$this->" . $column->getName() . " = $" . strtolower($module) . "->" . $column->getName() . ";\n";
            }
            $entityContent = str_replace('{{fields}}', $entityFields, $entityContent);
            $entityContent = str_replace('{{mapToDomainModelFields}}', $mapToDomainModelFields, $entityContent);
            $entityContent = str_replace('{{mapFromDomainModelFields}}', $mapFromDomainModelFields, $entityContent);
            file_put_contents($entityPath, $entityContent);
            $viewPath = $tsPath . $module . '.ts';
            $viewTemplate = dirname(__FILE__) . '/templates/crud/view.txt';
            $viewContent = file_get_contents($viewTemplate);
            $viewContent = str_replace('{{module}}', $module, $viewContent);
            $viewContent = str_replace('{{l-module}}', strtolower($module), $viewContent);
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
                    $formType = "FormType.HIDDEN";
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
            $twigPath = $htmlPath . 'index.html.twig';
            $twigTemplate = dirname(__FILE__) . '/templates/crud/index.txt';
            $twigContent = file_get_contents($twigTemplate);
            $twigContent = str_replace('{{module}}', $module, $twigContent);
            $twigContent = str_replace('{{l-module}}', strtolower($module), $twigContent);
            file_put_contents($twigPath, $twigContent);
            $io->success('Success');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error ' . $e->getMessage());
            return Command::FAILURE;
        }
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
}