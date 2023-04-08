<?php

namespace App\Maker;

use App\Core\Domain\Util\StringUtils;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

class SimpleCrudMaker implements CrudMakerInterface
{
    private Table $entity;

    /**
     * @template T of AbstractPlatform
     *
     * @param SymfonyStyle $io
     * @param AbstractSchemaManager<T> $schemaManager
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $isInteractive
     * @param bool $overwrite
     */
    public function __construct(
        private readonly SymfonyStyle          $io,
        private readonly AbstractSchemaManager $schemaManager,
        private readonly string                $moduleName,
        private readonly string                $modulePath,
        private readonly bool                  $isInteractive,
        private readonly bool                  $overwrite,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function makeCrud(): void
    {
        $this->io->title('Simple crud generator');
        $this->io->text('This will generate a simple crud for a given entity');
        $this->io->newLine();
        $this->io->text('Please select the entity');
        $tables = $this->schemaManager->listTableNames();
        $entityName = $this->io->choice('Entity', $tables);
        $this->entity = $this->schemaManager->introspectTable(strval($entityName));
        $this->makeController();
        $this->makeRepositoryInterface();
        $this->makeCreateUseCase();
        $this->makeUpdateUseCase();
        $this->makeDeleteUseCase();
        $this->makeGetAllUseCase();
        $this->makeGetOneUseCase();
        $this->makeModel();
        $this->makeEntity();
        $this->makeRepository();
        $this->makeDto();
        $this->makeProvider();
        $this->makeProcessor();
        $this->makeView();
        $this->makeTemplate();
    }

    private function makeController(): void
    {
        $applicationPath = $this->modulePath . '/Application/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the controller?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($applicationPath)) {
            mkdir($applicationPath, 0777, true);
        }
        $this->io->text('Generating controller');
        $controllerPath = $applicationPath . StringUtils::toPascalCase($this->moduleName) . 'Controller.php';
        if (!$this->overwrite) {
            if (file_exists($controllerPath)) {
                $confirm = $this->io->confirm('Controller already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $controllerTemplate = dirname(__FILE__) . '/skeleton/crud/controller.tpl';
        $controllerContent = file_get_contents($controllerTemplate);
        if ($controllerContent === false) {
            throw new \RuntimeException('Could not read controller template');
        }
        $controllerContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $controllerContent);
        $controllerContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $controllerContent);
        $controllerContent = str_replace('{{kebabModule}}', StringUtils::toKebabCase($this->moduleName), $controllerContent);

        file_put_contents($controllerPath, $controllerContent);
    }

    private function makeRepositoryInterface(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the repository interface?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating repository interface');
        $repositoryInterfacePath = $domainPath . StringUtils::toPascalCase($this->moduleName) . 'RepositoryInterface.php';
        if (!$this->overwrite) {
            if (file_exists($repositoryInterfacePath)) {
                $confirm = $this->io->confirm('Repository interface already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $repositoryInterfaceTemplate = dirname(__FILE__) . '/skeleton/crud/repository-interface.tpl';
        $repositoryInterfaceContent = file_get_contents($repositoryInterfaceTemplate);
        if ($repositoryInterfaceContent === false) {
            throw new \RuntimeException('Could not read repository interface template');
        }
        $repositoryInterfaceContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $repositoryInterfaceContent);
        $repositoryInterfaceContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $repositoryInterfaceContent);
        $repositoryInterfaceContent = str_replace('{{entityIdParam}}', Util::getEntityIdParam($this->entity), $repositoryInterfaceContent);

        file_put_contents($repositoryInterfacePath, $repositoryInterfaceContent);
    }

    private function makeCreateUseCase(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the create use case?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating create use case');
        $createUseCasePath = $domainPath . 'Create' . StringUtils::toPascalCase($this->moduleName) . 'UseCase.php';
        if (!$this->overwrite) {
            if (file_exists($createUseCasePath)) {
                $confirm = $this->io->confirm('Create use case already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $createUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/create.tpl';
        $createUseCaseContent = file_get_contents($createUseCaseTemplate);
        if ($createUseCaseContent === false) {
            throw new \RuntimeException('Could not read create use case template');
        }
        $createUseCaseContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $createUseCaseContent);
        $createUseCaseContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $createUseCaseContent);

        file_put_contents($createUseCasePath, $createUseCaseContent);
    }

    private function makeUpdateUseCase(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the update use case?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating update use case');
        $updateUseCasePath = $domainPath . 'Update' . StringUtils::toPascalCase($this->moduleName) . 'UseCase.php';
        if (!$this->overwrite) {
            if (file_exists($updateUseCasePath)) {
                $confirm = $this->io->confirm('Update use case already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $updateUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/update.tpl';
        $updateUseCaseContent = file_get_contents($updateUseCaseTemplate);
        if ($updateUseCaseContent === false) {
            throw new \RuntimeException('Could not read update use case template');
        }
        $updateUseCaseContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $updateUseCaseContent);
        $updateUseCaseContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $updateUseCaseContent);

        file_put_contents($updateUseCasePath, $updateUseCaseContent);
    }

    private function makeDeleteUseCase(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the delete use case?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating delete use case');
        $deleteUseCasePath = $domainPath . 'Delete' . StringUtils::toPascalCase($this->moduleName) . 'UseCase.php';
        if (!$this->overwrite) {
            if (file_exists($deleteUseCasePath)) {
                $confirm = $this->io->confirm('Delete use case already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $deleteUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/delete.tpl';
        $deleteUseCaseContent = file_get_contents($deleteUseCaseTemplate);
        if ($deleteUseCaseContent === false) {
            throw new \RuntimeException('Could not read delete use case template');
        }
        $deleteUseCaseContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $deleteUseCaseContent);
        $deleteUseCaseContent = str_replace('{{entityIdParam}}', Util::getEntityIdParam($this->entity), $deleteUseCaseContent);

        file_put_contents($deleteUseCasePath, $deleteUseCaseContent);
    }

    private function makeGetAllUseCase(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the get all use case?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating get all use case');
        $getAllUseCasePath = $domainPath . 'GetAll' . StringUtils::toPascalCase($this->moduleName) . 'UseCase.php';
        if (!$this->overwrite) {
            if (file_exists($getAllUseCasePath)) {
                $confirm = $this->io->confirm('Get all use case already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $getAllUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/get-all.tpl';
        $getAllUseCaseContent = file_get_contents($getAllUseCaseTemplate);
        if ($getAllUseCaseContent === false) {
            throw new \RuntimeException('Could not read get all use case template');
        }
        $getAllUseCaseContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $getAllUseCaseContent);

        file_put_contents($getAllUseCasePath, $getAllUseCaseContent);
    }

    private function makeGetOneUseCase(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the get one use case?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating get one use case');
        $getOneUseCasePath = $domainPath . 'GetOne' . StringUtils::toPascalCase($this->moduleName) . 'UseCase.php';
        if (!$this->overwrite) {
            if (file_exists($getOneUseCasePath)) {
                $confirm = $this->io->confirm('Get one use case already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $getOneUseCaseTemplate = dirname(__FILE__) . '/skeleton/crud/get-one.tpl';
        $getOneUseCaseContent = file_get_contents($getOneUseCaseTemplate);
        if ($getOneUseCaseContent === false) {
            throw new \RuntimeException('Could not read get one use case template');
        }
        $getOneUseCaseContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $getOneUseCaseContent);
        $getOneUseCaseContent = str_replace('{{entityIdParam}}', Util::getEntityIdParam($this->entity), $getOneUseCaseContent);

        file_put_contents($getOneUseCasePath, $getOneUseCaseContent);
    }

    private function makeModel(): void
    {
        $domainPath = $this->modulePath . '/Domain/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the model?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0777, true);
        }
        $this->io->text('Generating model');
        $modelPath = $domainPath . StringUtils::toPascalCase($this->moduleName) . '.php';
        if (!$this->overwrite) {
            if (file_exists($modelPath)) {
                $confirm = $this->io->confirm('Model already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $columns = $this->entity->getColumns();
        $modelTemplate = dirname(__FILE__) . '/skeleton/crud/model.tpl';
        $modelContent = file_get_contents($modelTemplate);
        if ($modelContent === false) {
            throw new \RuntimeException('Could not read model template');
        }
        $modelContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $modelContent);
        $modelFields = '';
        foreach ($columns as $column) {
            $columnType = $column->getType()->getName();
            $default = ($columnType === 'boolean') ? 'false' : 'null';
            $modelFields .= "        #[SerializedName('" . $column->getName() . "')]\n";
            $modelFields .= '        public ?' . Util::getType($columnType) . ' $' . $column->getName() . " = $default,\n";
        }
        $modelContent = str_replace('{{fields}}', substr($modelFields, 0, -2), $modelContent);

        file_put_contents($modelPath, $modelContent);
    }

    private function makeEntity(): void
    {
        $infrastructurePath = $this->modulePath . '/Infrastructure/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the entity?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($infrastructurePath)) {
            mkdir($infrastructurePath, 0777, true);
        }
        $this->io->text('Generating entity');
        $entityPath = $infrastructurePath . StringUtils::toPascalCase($this->moduleName) . 'Entity.php';
        if (!$this->overwrite) {
            if (file_exists($entityPath)) {
                $confirm = $this->io->confirm('Entity already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $columns = $this->entity->getColumns();
        $primaryKey = $this->entity->getPrimaryKey();
        $entityTemplate = dirname(__FILE__) . '/skeleton/crud/entity.tpl';
        $entityContent = file_get_contents($entityTemplate);
        if ($entityContent === false) {
            throw new \RuntimeException('Could not read entity template');
        }
        $entityContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $entityContent);
        $entityContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $entityContent);
        $entityContent = str_replace('{{table}}', $this->entity->getName(), $entityContent);
        $entityFields = '';
        foreach ($columns as $column) {
            if (!empty($primaryKey?->getColumns()) && $column->getName() == $primaryKey->getColumns()[0]) {
                $entityFields .= "        #[ORM\Id]\n";
                $entityFields .= "        #[ORM\GeneratedValue]\n";
            }
            $entityFields .= "        #[ORM\Column(name: '" . $column->getName() . "', type: '" . $column->getType()->getName() . "')]\n";
            $entityFields .= "        public ?" . Util::getType($column->getType()->getName()) . " $" . $column->getName() . " = null,\n";
        }
        $entityContent = str_replace('{{fields}}', substr($entityFields, 0, -2), $entityContent);

        file_put_contents($entityPath, $entityContent);
    }

    private function makeRepository(): void
    {
        $infrastructurePath = $this->modulePath . '/Infrastructure/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the repository?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($infrastructurePath)) {
            mkdir($infrastructurePath, 0777, true);
        }
        $this->io->text('Generating repository');
        $repositoryPath = $infrastructurePath . StringUtils::toPascalCase($this->moduleName) . 'Repository.php';
        if (!$this->overwrite) {
            if (file_exists($repositoryPath)) {
                $confirm = $this->io->confirm('Repository already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $primaryKey = $this->entity->getPrimaryKey();
        $repositoryTemplate = dirname(__FILE__) . '/skeleton/crud/repository.tpl';
        $repositoryContent = file_get_contents($repositoryTemplate);
        if ($repositoryContent === false) {
            throw new \RuntimeException('Could not read repository template');
        }
        $repositoryContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $repositoryContent);
        $repositoryContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $repositoryContent);
        $repositoryContent = str_replace('{{table}}', $this->entity->getName(), $repositoryContent);
        $repositoryContent = str_replace('{{entityId}}', strval($primaryKey?->getColumns()[0]), $repositoryContent);
        $repositoryContent = str_replace('{{entityIdParam}}', Util::getEntityIdParam($this->entity), $repositoryContent);

        file_put_contents($repositoryPath, $repositoryContent);
    }

    private function makeDto(): void
    {
        $apiPath = $this->modulePath . '/Api/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the DTO?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($apiPath)) {
            mkdir($apiPath, 0777, true);
        }
        $this->io->text('Generating dto');
        $dtoPath = $apiPath . StringUtils::toPascalCase($this->moduleName) . 'Dto.php';
        if (!$this->overwrite) {
            if (file_exists($dtoPath)) {
                $confirm = $this->io->confirm('DTO already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $dtoTemplate = dirname(__FILE__) . '/skeleton/crud/dto.tpl';
        $dtoContent = file_get_contents($dtoTemplate);
        if ($dtoContent === false) {
            throw new \RuntimeException('Could not read dto template');
        }
        $dtoContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $dtoContent);
        $dtoContent = str_replace('{{entityIdField}}', Util::getEntityIdField($this->entity), $dtoContent);

        file_put_contents($dtoPath, $dtoContent);
    }

    private function makeProcessor(): void
    {
        $apiPath = $this->modulePath . '/Api/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the processor?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($apiPath)) {
            mkdir($apiPath, 0777, true);
        }
        $this->io->text('Generating processor');
        $processorPath = $apiPath . StringUtils::toPascalCase($this->moduleName) . 'Processor.php';
        if (!$this->overwrite) {
            if (file_exists($processorPath)) {
                $confirm = $this->io->confirm('Processor already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $primaryKey = $this->entity->getPrimaryKey();
        $processorTemplate = dirname(__FILE__) . '/skeleton/crud/processor.tpl';
        $processorContent = file_get_contents($processorTemplate);
        if ($processorContent === false) {
            throw new \RuntimeException('Could not read processor template');
        }
        $processorContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $processorContent);
        $processorContent = str_replace('{{entityId}}', strval($primaryKey?->getColumns()[0]), $processorContent);

        file_put_contents($processorPath, $processorContent);
    }

    private function makeProvider(): void
    {
        $apiPath = $this->modulePath . '/Api/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the provider?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($apiPath)) {
            mkdir($apiPath, 0777, true);
        }
        $this->io->text('Generating provider');
        $providerPath = $apiPath . StringUtils::toPascalCase($this->moduleName) . 'Provider.php';
        if (!$this->overwrite) {
            if (file_exists($providerPath)) {
                $confirm = $this->io->confirm('Provider already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $columns = [];
        foreach ($this->entity->getColumns() as $column) {
            $columns[] = $column->getName();
        }
        $sortColumn = $this->io->choice('Which column do you want to use for sorting?', $columns);
        $sortOrder = $this->io->choice('Which order do you want to use for sorting?', ['asc', 'desc'], 'asc');
        $primaryKey = $this->entity->getPrimaryKey();
        $providerTemplate = dirname(__FILE__) . '/skeleton/crud/provider.tpl';
        $providerContent = file_get_contents($providerTemplate);
        if ($providerContent === false) {
            throw new \RuntimeException('Could not read provider template');
        }
        $idColumn = Util::getIdColumn($this->entity);
        $providerContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $providerContent);
        $providerContent = str_replace('{{castType}}', Util::getCastType(Util::getColumnType($idColumn)), $providerContent);
        $providerContent = str_replace('{{entityId}}', strval($primaryKey?->getColumns()[0]), $providerContent);
        $providerContent = str_replace('{{sortColumn}}', strval($sortColumn), $providerContent);
        $providerContent = str_replace('{{sortOrder}}', strval($sortOrder), $providerContent);

        file_put_contents($providerPath, $providerContent);
    }

    private function makeView(): void
    {
        $jsPath = dirname(__DIR__, 2) . '/assets/pages/';
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the view?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($jsPath)) {
            mkdir($jsPath, 0777, true);
        }
        $this->io->text('Generating view');
        $viewFile = $jsPath . StringUtils::toPascalCase($this->moduleName) . '.ts';
        if (!$this->overwrite) {
            if (file_exists($viewFile)) {
                $confirm = $this->io->confirm('View already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $viewTemplate = dirname(__FILE__) . '/skeleton/crud/view.tpl';
        $viewContent = file_get_contents($viewTemplate);
        if ($viewContent === false) {
            throw new \RuntimeException('Could not read view template');
        }
        $viewContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $viewContent);
        $viewContent = str_replace('{{kebabModule}}', StringUtils::toKebabCase($this->moduleName), $viewContent);
        $viewFields = Util::getViewFields($this->entity);
        $viewContent = str_replace('{{fields}}', substr($viewFields, 0, -2), $viewContent);

        file_put_contents($viewFile, $viewContent);
    }

    private function makeTemplate(): void
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/' . StringUtils::toKebabCase($this->moduleName);
        if ($this->isInteractive) {
            $confirm = $this->io->confirm('Do you want to generate the template?');
            if (!$confirm) {
                return;
            }
        }
        if (!is_dir($templatePath)) {
            mkdir($templatePath, 0777, true);
        }
        $this->io->text('Generating template');
        $templateFile = $templatePath . '/index.html.twig';
        if (!$this->overwrite) {
            if (file_exists($templateFile)) {
                $confirm = $this->io->confirm('Template already exists, do you want to overwrite it?');
                if (!$confirm) {
                    return;
                }
            }
        }
        $twigTemplate = dirname(__FILE__) . '/skeleton/crud/index.tpl';
        $twigContent = file_get_contents($twigTemplate);
        if ($twigContent === false) {
            throw new \RuntimeException('Could not read twig template');
        }
        $twigContent = str_replace('{{module}}', StringUtils::toPascalCase($this->moduleName), $twigContent);
        $twigContent = str_replace('{{camelModule}}', StringUtils::toCamelCase($this->moduleName), $twigContent);
        file_put_contents($templateFile, $twigContent);
    }
}