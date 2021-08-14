<?php

namespace App\Controller;

use App\Model\BackupModel;

class BackupController extends BaseController
{
    private BackupModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new BackupModel();
    }

    public function index()
    {
        $this->render('backup/index.html.twig');
    }

    public function get()
    {
        $this->json($this->model->get());
    }

    public function insert()
    {
        $this->json($this->model->insert());
    }

    public function update()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->update($id));
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->delete($id));
    }

    public function download()
    {
        $id = $this->app->get('PARAMS.id');
        $backup = $this->model->getOneById($id);

        $file = $backup[0]['path'];

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header("Content-Type: text/plain");
            header('Content-Disposition: attachment; filename=' . $file);
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            readfile($file);
            exit();
        } else {
            $this->app->error(404, 'Not Found');
        }
    }
}
    