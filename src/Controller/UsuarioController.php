<?php

namespace App\Controller;

use App\Model\UsuarioModel;

class UsuarioController extends BaseController
{

    private UsuarioModel $model;

    function __construct()
    {
        parent::__construct();
        $this->model = new UsuarioModel();
    }

    function beforeroute()
    {
        //needed for avoid redirect loop
    }

    function index()
    {
        $this->render('usuario/index.html.twig');
    }

    function get()
    {
        $result = $this->model->all();
        $this->json($result);
    }

    function add()
    {
        $data = $this->model->add();
        $this->json($data);
    }

    function edit()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->edit($id);
        $this->json($data);
    }

    function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->delete($id);
        $this->json($data);
    }

    function login()
    {
        if ($this->app->get('SESSION.usuario') !== null) {
            $this->app->reroute('/home');
            exit;
        } else {
            $this->model->init();
            $this->render('login/index.html.twig', [
                'title' => $this->app->get('TITLE')
            ]);
        }
    }

    function autenticar()
    {
        $username = $this->app->get('POST.username');
        $password = $this->app->get('POST.password');

        $user = $this->model->getByName($username);

        if (!$user) {
            $this->json(array('msg' => 'Usuario y/o Contraseña Erroneos'));
        } else {
            if ($user['estado'] == 1 && password_verify($password, $user['password'])) {
                $this->app->set('SESSION.usuario_id', $user['usuario_id']);
                $this->app->set('SESSION.usuario', $user['username']);
                $this->app->set('SESSION.rol_id', $user['rol_id']);
                $this->json(array('success' => true, 'msg' => 'Bienvenido.'));
            } else {
                $this->json(array('msg' => 'Usuario y/o Contraseña Incorrectos'));
            }
        }
    }

    function app_login()
    {
        $username = $this->app->get('POST.username');
        $password = $this->app->get('POST.password');

        $this->model->getByName($username);
        $usuario = $this->model->getOne($username);

        if ($this->model->dry()) {
            $this->json(array('msg' => 'Usuario y/o Contraseña Erroneos'));
        } else {
            if ($this->model->estado == 1 && password_verify($password, $this->model->password)) {
                $this->json(array('success' => true, 'usuario' => $usuario));
            } else {
                $this->json(array('msg' => 'Usuario y/o Contraseña Incorrectos'));
            }
        }
    }

    function app_pin_login()
    {
        $pin = $this->app->get('POST.pin');

        $this->model->getByPin($pin);
        $usuario = $this->model->getOne($this->model->username);

        if ($this->model->dry()) {
            $this->json(array('msg' => 'Usuario y/o Contraseña Erroneos'));
        } else {
            if ($this->model->estado == 1) {
                $this->json(array('success' => true, 'usuario' => $usuario));
            } else {
                $this->json(array('msg' => 'Usuario y/o Contraseña Incorrectos'));
            }
        }
    }

    function salir()
    {
        $this->app->clear('SESSION');
        $this->app->reroute('/login');
    }

    function password()
    {
        $this->render('change-password/index.html.twig');
    }

    function changePassword()
    {
        $id = $this->app->get('SESSION.usuario_id');
        $user = $this->model->getById($id);
        if (password_verify($_POST['actual'], $user['password'])) {
            $this->json($this->model->changePassword($id));
        } else {
            $this->json(array('msg' => 'Contraseña actual incorrecta'));
        }
    }
}
