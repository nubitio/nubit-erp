<?php

namespace App\Controller;

use App\Domain\Service\SerializerService;
use App\Extension\AppExtension;
use App\Model\OptionsModel;
use App\Service\OptionBuilder;
use App\Service\FileUploader;
use Base;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class BaseController
{
    protected Base $app;
    protected Environment $twig;
    protected ContainerInterface $container;
    protected OptionsModel $options;
    protected SerializerService $serializer;
    protected FileUploader $uploader;
    protected Request $request;
    protected $current_user_id;
    protected $current_local_id;
    protected $current_almacen_id;

    public function beforeroute()
    {
        if ($this->app->get('SESSION.usuario') === null) {
            $this->app->reroute('/login');
            exit;
        }
    }

    public function __construct()
    {
        /** @var Base $app */
        $app = Base::instance();
        $this->app = $app;
        date_default_timezone_set('America/Lima');
        setlocale(LC_NUMERIC, 'C');
        $this->container = $app->get('di');

        $loader = new FilesystemLoader('../templates');
        $this->twig = new Environment($loader, [
            'debug' => true,
            'cache' => '../public/tmp/',
        ]);

        $assetsVersion = "v$_ENV[APP_VERSION]";
        $this->twig->addExtension(new AssetExtension(new Packages(new PathPackage('', new StaticVersionStrategy($assetsVersion, '%s?version=%s')))));
        $optionBuilder = $this->container->get(OptionBuilder::class);
        $this->twig->addExtension(new EntryFilesTwigExtension($app->get('di')));
        $this->twig->addExtension(new AppExtension($optionBuilder));

        $this->options = new OptionsModel();
        $this->serializer = $this->container->get(SerializerService::class);
        $this->uploader = $this->container->get(FileUploader::class);

        $this->current_user_id = $this->app->get('SESSION.usuario_id');
        $this->current_local_id = $this->app->get('SESSION.local_id');
        $this->current_almacen_id = $this->app->get('SESSION.default_almacen');

        $this->request = $app->get('request');
    }

    public function render(string $view, array $parameters = [])
    {
        try {
            echo $this->twig->render($view, $parameters);
        } catch (\Exception $e) {
            $this->app->error(500, $e->getMessage(), $e->getTrace());
        }
    }

    public function renderView(string $view, array $parameters = []): ?string
    {
        try {
            return $this->twig->render($view, $parameters);
        } catch (\Exception $e) {
            $this->app->error(500, $e->getMessage(), $e->getTrace());
            return null;
        }
    }

    public function json(mixed $data, int $status = 200, array $headers = [], array $context = [])
    {
        $json = $this->serializer->serialize($data, 'json', array_merge([
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
        ], $context));

        $response = new JsonResponse($json, $status, $headers, true);
        $response->send();
    }

    public function export($data, $extras, $kardex = null)
    {
        $letters = range('A', 'Z');
        $calc = new Spreadsheet();
        $header_style = array(
            'font'      => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ),
            'fill'      => array(
                'fillType' => Fill::FILL_SOLID,
                'color'    => array('rgb' => 'D3D7CF')
            ),
        );
        $center_style = array(
            'font'      => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            )
        );
        $border_style = array(
            'borders' => array(
                'allBorders' => array('borderStyle' => Border::BORDER_THIN)
            )
        );
        try {
            $calc->
            getProperties()
                ->setCreator("JGM90")
                ->setTitle($extras['title']);
            $calc->setActiveSheetIndex(0);
            $calc->getActiveSheet()->setCellValue('A1', $extras['title']);
            $calc->getActiveSheet()->setCellValue('A2', $extras['subtitle']);
            $headers = array_keys($data[0]);
            $calc->getActiveSheet()->mergeCells('A1:' . $letters[count($headers) - 1] . '1');
            $calc->getActiveSheet()->mergeCells('A2:' . $letters[count($headers) - 1] . '2');
            for ($i = 0; $i < count($headers); $i++) {
                $header = str_replace('_', ' ', $headers[$i]);
                $calc->getActiveSheet()->setCellValue($letters[$i] . '3', strtoupper($header));
            }
            $rows = count($data) + 3;
            if ($kardex != null) {
                $calc->getActiveSheet()->setCellValue('A4', '');
                $calc->getActiveSheet()->setCellValue('B4', 'SALDO ANTERIOR');
                $calc->getActiveSheet()->setCellValue('C4', '');
                $calc->getActiveSheet()->setCellValue('D4', '');
                $calc->getActiveSheet()->setCellValue('E4', $kardex);
                $rows = count($data) + 4;
            }
            if (isset($extras['sumcols']) && count($extras['sumcols']) > 0) {
                $lastrow = $rows + 1;
                $calc->getActiveSheet()->setCellValue('A' . $lastrow, 'TOTAL');
                for ($i = 0; $i < count($extras['sumcols']); $i++) {
                    $col = $letters[$extras['sumcols'][$i]];
                    $calc->getActiveSheet()->setCellValue($col . $lastrow, '=SUM(' . $col . '2:' . $col . $rows . ')');
                }
                $rows = $lastrow;
                $calc->getActiveSheet()->getStyle('A' . $rows . ':' . $letters[count($headers) - 1] . $rows)->applyFromArray($header_style);
            }
            $calc->getActiveSheet()->getStyle('A1:' . $letters[count($headers) - 1] . '2')->applyFromArray($center_style);
            $calc->getActiveSheet()->getStyle('A3:' . $letters[count($headers) - 1] . '3')->applyFromArray($header_style);
            if ($kardex != null) {
                $calc->getActiveSheet()->fromArray($data, null, 'A5');
            } else {
                $calc->getActiveSheet()->fromArray($data, null, 'A4');
            }
            $calc->getActiveSheet()->setTitle($extras['title']);
            $calc->getActiveSheet()->getStyle('A1:' . $letters[count($headers) - 1] . $rows)->applyFromArray($border_style);
            foreach (range('A', $letters[count($headers) - 1]) as $columnID) {
                $calc->getActiveSheet()->getColumnDimension($columnID)
                    ->setAutoSize(true);
            }
            $filename = str_replace('', '_', $extras['title']);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . strtolower($filename) . '.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($calc);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @throws \Exception
     */
    public function report($html, $filename = 'download.pdf')
    {
        // Run wkhtmltopdf
        $descriptorspec = array(
            0 => array('pipe', 'r'), // stdin
            1 => array('pipe', 'w'), // stdout
            2 => array('pipe', 'w'), // stderr
        );
        $process = proc_open('wkhtmltopdf --viewport-size 1024x768 -q - -', $descriptorspec, $pipes);
        // Send the HTML on stdin
        fwrite($pipes[0], $html);
        fclose($pipes[0]);
        // Read the outputs
        $pdf = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        // Close the process
        fclose($pipes[1]);
        $return_value = proc_close($process);
        // Output the results
        if ($errors) {
            throw new \Exception('PDF generation failed: ' . $errors);
        } else {
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf));
            header('Content-Disposition: inline; filename="' . $filename . '";');
            echo $pdf;
        }
    }

    public function base64($path)
    {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    public function money_pen($value)
    {
        return 'S/ ' . number_format($value, 2);
    }

    public function action($route)
    {
        return $this->app->get('BASE_URL') . $route;
    }
}