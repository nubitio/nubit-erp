<?php

namespace App\Model;

use App\Util\ModelUtils;
use Base;
use DB\SQL;

class BaseModel
{
    protected $app;
    protected $db;
    protected $utils;
    protected $current_user_id;
    protected $current_local_id;
    protected $current_almacen_id;

    public function __construct()
    {
        /** @var Base $app */
        $this->app = Base::instance();
        $cn = 'mysql:host=' . $this->app->get('DB_HOST') . ';port=' . $this->app->get('DB_PORT') . ';dbname=' . $this->app->get('DB_NAME');
        $db = new SQL(
            $cn,
            $this->app->get('DB_USER'),
            $this->app->get('DB_PASSWORD'),
            array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            )
        );
        $this->db = $db;
        $this->utils = new ModelUtils($db);
        $this->current_user_id = $this->app->get('SESSION.usuario_id');
        $this->current_local_id = $this->app->get('SESSION.local_id');
        $this->current_almacen_id = $this->app->get('SESSION.default_almacen');
    }
}