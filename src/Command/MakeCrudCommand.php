<?php

namespace App\Command;

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
            $table = $io->choice('Please select the table', $tables);
            $columns = $this->connection->getSchemaManager()->listTableColumns($table);
            $fields = [];
            foreach ($columns as $column) {
                $fields[] = $column->getName();
            }
            $fields = implode(', ', $fields);
            $io->text($fields);
            //create the entity class file in src/Infrastructure/Entity
            //mkdir('src/Infrastructure/Entity', 0777, true);
            //$file = fopen('src/Infrastructure/Entity/' . $table . '.php', 'w');
            $io->text('This is the generated code');
            $io->text('<?php');
            $io->text('');
            $io->text('namespace App\Controller;');
            $io->text('');
            $io->text('use App\Entity\\' . ucfirst($table) . ';');
            $io->text('');
            $io->text('class ' . ucfirst($table) . 'Controller extends AbstractController');
            $io->text('{');
            $io->text('    public function index()');
            $io->text('    {');
            $io->text('        $' . $table . ' = $this->getDoctrine()->getRepository(\\' . ucfirst($table) . '::class)->findAll();');
            $io->text('        return $this->render(\'index.html.twig\', [');
            $io->text('            \'' . $table . '\' => $' . $table . ',');
            $io->text('        ]);');
            $io->text('    }');
            $io->text('');
            $io->text('    public function new()');
            $io->text('    {');
            $io->text('        $' . $table . ' = new \\' . ucfirst($table) . '();');
            $io->text('        $form = $this->createForm(\\' . ucfirst($table) . 'Type::class, $' . $table . ');');
            $io->text('        $form->handleRequest($request);');
            $io->text('        if ($form->isSubmitted() && $form->isValid()) {');
            $io->text('            $entityManager = $this->getDoctrine()->getManager();');
            $io->text('            $entityManager->persist($' . $table . ');');
            $io->text('            $entityManager->flush();');
            $io->text('            return $this->redirectToRoute(\'' . $table . '\');');
            $io->text('        }');
            $io->text('        return $this->render(\'new.html.twig\', [');
            $io->text('            \'form\' => $form->createView(),');
            $io->text('        ]);');
            $io->text('    }');
            $io->text('');
            $io->text('    public function show($id)');
            $io->text('    {');
            $io->text('        $' . $table . ' = $this->getDoctrine()->getRepository(\\' . ucfirst($table) . '::class)->find($id);');
            $io->text('        return $this->render(\'show.html.twig\', [');
            $io->text('            \'' . $table . '\' => $' . $table . ',');
            $io->text('        ]);');
            $io->text('    }');
            $io->text('');
            $io->text('    public function edit($id)');
            $io->text('    {');
            $io->text('        $' . $table . ' = $this->getDoctrine()->getRepository(\\' . ucfirst($table) . '::class)->find($id);');
            $io->text('        $form = $this->createForm(\\' . ucfirst($table) . 'Type::class, $' . $table . ');');
            $io->text('        $form->handleRequest($request);');
            $io->text('        if ($form->isSubmitted() && $form->isValid()) {');
            $io->text('            $entityManager = $this->getDoctrine()->getManager();');
            $io->text('            $entityManager->persist($' . $table . ');');
            $io->text('            $entityManager->flush();');
            $io->text('            return $this->redirectToRoute(\'' . $table . '\');');
            $io->text('        }');
            $io->text('        return $this->render(\'edit.html.twig\', [');
            $io->text('            \'form\' => $form->createView(),');
            $io->text('        ]);');
            $io->text('    }');
            $io->text('');
            $io->text('    public function delete($id)');
            $io->text('    {');
            $io->text('        $' . $table . ' = $this->getDoctrine()->getRepository(\\' . ucfirst($table) . '::class)->find($id);');
            $io->text('        $entityManager = $this->getDoctrine()->getManager();');
            $io->text('        $entityManager->remove($' . $table . ');');
            $io->text('        $entityManager->flush();');
            $io->text('        return $this->redirectToRoute(\'' . $table . '\');');
            $io->text('    }');
            $io->text('}');
            $io->text('');
            $io->text('?>');
            $io->success('Success');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}