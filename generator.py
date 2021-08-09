#!/usr/bin/env python3

import getopt
import os
import sys
import pymysql.cursors


def main(argv):
    module = ''
    try:
        opts, argv = getopt.getopt(argv, "hm:", ["module="])
    except getopt.GetoptError:
        print('generator.py -m <module>')
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print('generator.py -m <module>')
            sys.exit()
        elif opt in ("-m", "module"):
            module = arg
            do_generation(module)


def do_generation(module):
    path = os.getcwd()
    pages_path = "public/assets/js/pages/" + module
    js_path = pages_path + "/index.js"
    print("The current working directory is %s" % path)
    connection = pymysql.connect(host='localhost', user='c3_admin', password='Admin.3000', db='c3_efact',
                                 charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor)
    try:
        result = {}
        try:
            with connection.cursor() as cursor:
                sql = 'show columns from ' + module + ''
                cursor.execute(sql)
                result = cursor.fetchall()
        finally:
            connection.close()

        ignored = ['id', f'{module}_id', 'created_at', 'updated_at', 'created_by', 'updated_by']

        generate_controller(module)
        generate_model(module)
        generate_template(module, result, ignored)

        if not os.path.exists(pages_path):
            os.mkdir(pages_path)
            print("Folder created %s" % pages_path)

        js = open(js_path, 'w')
        js.write('var app = app || {};\n')
        js.write('app.' + module + ' = (function () {\n\n')
        js.write('    let route;\n')
        js.write('    let dg = $("#' + module + '_dg");\n')
        js.write('    let dlg = $("#' + module + '_dlg");\n')
        js.write('    let fm = $("#' + module + '_fm");\n')
        for row in result:
            field = row['Field']
            if not(field in ignored):
                js.write('    let txt_' + field + ' = $("#' + module + '_' + field + '");\n')
        js.write('    let btnOk;\n')
        js.write('    let btnCancel;\n\n')
        js.write('    let init = function () {\n')
        js.write('        dg.datagrid({\n')
        js.write('            title: "' + module.title() + '",\n')
        js.write('            url: "' + module + '/get",\n')
        js.write('            remoteFilter: true,\n')
        js.write('            toolbar: [{\n')
        js.write('                text: TEXT_ADD,\n')
        js.write('                iconCls: ICON_ADD,\n')
        js.write('                handler: function () {\n')
        js.write('                    insert();\n')
        js.write('                }\n')
        js.write('            }, "-", {\n')
        js.write('                text: TEXT_EDIT,\n')
        js.write('                iconCls: ICON_EDIT,\n')
        js.write('                handler: function () {\n')
        js.write('                    update();\n')
        js.write('                }\n')
        js.write('            }, "-", {\n')
        js.write('                text: TEXT_DELETE,\n')
        js.write('                iconCls: ICON_DELETE,\n')
        js.write('                handler: function () {\n')
        js.write('                    erase();\n')
        js.write('                }\n')
        js.write('            }],\n')
        js.write('            columns: [[\n')
        for row in result:
            field = row['Field']
            if not(field in ignored):
                js.write('                {\n')
                js.write('                    field: "' + field + '",\n')
                js.write('                    title: "' + field.title().replace("_", " ") + '",\n')
                if (field == 'active'):
                    js.write('                    formatter: activeFormatter,\n')
                js.write('                },\n')
        js.write('            ]],\n')
        js.write('            onDblClickRow: function () {\n')
        js.write('                update();\n')
        js.write('            }\n')
        js.write('        });\n\n')
        js.write('        dg.datagrid("enableFilter", [{}]);\n\n')
        js.write('        dlg.dialog({\n')
        js.write('            buttons: [{\n')
        js.write('                id: "' + module + '_cancel",\n')
        js.write('                text: TEXT_CANCEL,\n')
        js.write('                handler: function () {\n')
        js.write('                    dlg.dialog("close");\n')
        js.write('                }\n')
        js.write('            }, {\n')
        js.write('                id: "' + module + '_ok",\n')
        js.write('                text: TEXT_SAVE,\n')
        js.write('                handler: function () {\n')
        js.write('                    save();\n')
        js.write('                }\n')
        js.write('            }],\n')
        js.write('            onOpen: function () {\n')
        js.write('                dlg.dialog("center");\n')
        js.write('                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);\n')
        js.write('                btnOk = $("#' + module + '_ok");\n')
        js.write('                btnCancel = $("#' + module + '_cancel");\n')
        js.write('                btnOk.addClass("md-btn");\n')
        js.write('                btnCancel.addClass("md-btn-plain");\n')
        js.write('            }\n')
        js.write('        });\n\n')
        for row in result:
            print(row)
            field = row['Field']
            type = row['Type']
            required = row['Null'] == 'NO'
            required = str(required).lower()
            string_types = ['char', 'varchar', 'text']
            numeric_types = ['int', 'decimal', 'float', 'double']
            date_types = ['date', 'datetime']
            boolean_types = ['bit', 'tinyint']
            if not (field in ignored):
                if any(c in type for c in string_types):
                    js.write('        txt_' + field + '.textbox({\n')
                elif any(c in type for c in boolean_types):
                    js.write('        txt_' + field + '.switchbutton({\n')
                elif any(c in type for c in date_types):
                    js.write('        txt_' + field + '.datebox({\n')
                elif any(c in type for c in numeric_types):
                    if (field.endswith('_id')):
                        js.write('        txt_' + field + '.combobox({\n')
                    else:
                        js.write('        txt_' + field + '.numberbox({\n')
                else:
                    js.write(f'        txt_' + field + '.combobox({\n')
                if any(c in type for c in boolean_types):
                    js.write(f'            label: "{field.title().replace("_", " ")}",\n')
                    js.write('            labelPosition: "top",\n')
                    js.write('            onText: "SI",\n')
                    js.write('            offText: "NO",\n')
                    js.write('            value: "1",\n')
                else:
                    js.write('            width: "100%",\n')
                    js.write(f'            label: "{field.title().replace("_", " ")}",\n')
                    js.write('            labelPosition: "top",\n')
                if any(c in type for c in date_types):
                    js.write('            formatter: dateformatter,\n')
                    js.write('            parser: dateparser,\n')
                if (field.endswith('_id')):
                    js.write('            url: "",\n')
                    js.write('            method: "get",\n')
                    js.write('            valueField: "",\n')
                    js.write('            textField: "",\n')
                    js.write('            panelHeight: 200,\n')
                    js.write('            limitToList: true,\n')
                js.write(f'            required: {required}\n')
                js.write('        });\n\n')
        js.write('    };\n\n')
        js.write('    function insert() {\n')
        js.write('        fm.form("clear");\n')
        js.write('        route = "' + module + '/add";\n')
        js.write('        dlg.dialog("open").dialog("setTitle", "Nuevo");\n')
        js.write('    }\n\n')
        js.write('    function update() {\n')
        js.write('        let row = dg.datagrid("getSelected");\n')
        js.write('        if (row) {\n')
        js.write('            dlg.dialog("open").dialog("setTitle", "Editar");\n')
        js.write('            fm.form("load", row);\n')
        js.write('            route = "' + module + '/edit/" + row.id;\n')
        js.write('        } else {\n')
        js.write('            show_info("Seleccione un registro.");\n')
        js.write('        }\n')
        js.write('    }\n\n')
        js.write('    function save() {\n')
        js.write('        fm.form("submit", {\n')
        js.write('            url: route,\n')
        js.write('            onSubmit: function () {\n')
        js.write('                let valid = fm.form("validate");\n')
        js.write('                if (valid) {\n')
        js.write('                    disableButtons(btnOk, btnCancel, dlg);\n')
        js.write('                }\n')
        js.write('                return valid;\n')
        js.write('            },\n')
        js.write('            success: function (result) {\n')
        js.write('                try {\n')
        js.write('                    result = JSON.parse(result);\n')
        js.write('                    if (result.success) {\n')
        js.write('                        dlg.dialog("close");\n')
        js.write('                        dg.datagrid("reload");\n')
        js.write('                        show_info(result.msg);\n')
        js.write('                    } else {\n')
        js.write('                        show_error(result.msg);\n')
        js.write('                    }\n')
        js.write('                    enableButtons(btnOk, btnCancel, dlg);\n')
        js.write('                } catch (e) {\n')
        js.write('                    show_error(e);\n')
        js.write('                    enableButtons(btnOk, btnCancel, dlg);\n')
        js.write('                }\n')
        js.write('            }\n')
        js.write('        });\n')
        js.write('    }\n\n')
        js.write('    function erase() {\n')
        js.write('        let row = dg.datagrid("getSelected");\n')
        js.write('        if (row) {\n')
        js.write('            $.messager.confirm("Confirmar", TEXT_CONFIRM, function (r) {\n')
        js.write('                if (r) {\n')
        js.write('                    $.post("' + module + '/delete/" + row.id)\n')
        js.write('                        .done(function (result) {\n')
        js.write('                            if (result.success) {\n')
        js.write('                                dg.datagrid("reload");\n')
        js.write('                                show_info(result.msg);\n')
        js.write('                            } else {\n')
        js.write('                                show_error(result.msg);\n')
        js.write('                            }\n')
        js.write('                        })\n')
        js.write('                        .fail(function (xhr, status, error) {\n')
        js.write('                            show_error(error);\n')
        js.write('                        });\n')
        js.write('                }\n')
        js.write('            });\n')
        js.write('        } else {\n')
        js.write('            show_info("Seleccione un registro");\n')
        js.write('        }\n')
        js.write('    }\n\n')
        js.write('    return {\n')
        js.write('        init: init\n')
        js.write('    };\n')
        js.write('})();\n')
        js.write('app.' + module + '.init();\n')
        js.close()
        print("File created %s" % js_path)
        generate_routes(module)
    except OSError as e:
        print("OSError [{}]: {} at {}".format(e.errno, e.strerror, e.filename))

def generate_controller(module):
    controller_path = "src/Controllers/" + module.title() + "Controller.php"
    controller = open(controller_path, 'w')
    controller_text = f'''<?php

namespace App\\Controllers;

use App\\Models\\{module.title()}Model;

class {module.title()}Controller extends BaseController
{{
    private {module.title()}Model $model;

    public function __construct()
    {{
        parent::__construct();
        $this->model = new {module.title()}Model();
    }}

    public function index()
    {{
        $this->render('{module}/index.html.twig');
    }}

    public function get()
    {{
        $this->json($this->model->get());
    }}

    public function insert()
    {{
        $this->json($this->model->insert());
    }}

    public function update()
    {{
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->update($id));
    }}

    public function delete()
    {{
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->delete($id));
    }}
}}
    '''
    controller.write(controller_text)
    controller.close()
    print("File created %s" % controller_path)

def generate_model(module):
    model_path = "src/Models/" + module.title() + "Model.php"
    model = open(model_path, 'w')
    model_text = f'''<?php

namespace App\\Models;

use DB\\SQL\\Mapper;

class {module.title()}Model extends BaseModel
{{
    private Mapper $dao;

    public function __construct()
    {{
        parent::__construct();
        $this->dao = new Mapper($this->db, '{module}');
    }}

    public function get()
    {{
        return $this->utils->setUp('{module}', 'id', 'asc');
    }}

    public function insert()
    {{
        $this->dao->copyfrom('POST');
        try {{
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        }} catch (\\PDOException $e) {{
            return array('msg' => $e->getMessage());
        }}
    }}

    public function update($id)
    {{
        $this->dao->load(array('id=?', $id));
        $this->dao->copyfrom('POST');
        try {{
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        }} catch (\\PDOException $e) {{
            return array('msg' => $e->getMessage());
        }}
    }}

    public function delete($id)
    {{
        $this->dao->load(array('id=?', $id));
        try {{
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        }} catch (\\PDOException $e) {{
            return array('msg' => $e->getMessage());
        }}
    }}
}}
    '''
    model.write(model_text)
    model.close()
    print("File created %s" % model_path)

def generate_template(module, result, ignored):
    templates_path = "templates/" + module
    template_path = templates_path + "/index.html.twig"
    if not os.path.exists(templates_path):
        os.mkdir(templates_path)
        print("Folder created %s" % templates_path)

    template = open(template_path, 'w')
    template.write('<div class="wrapper">\n')
    template.write('    <table id="' + module + '_dg"></table>\n')
    template.write('    <!-- Dialog -->\n')
    template.write('    <div id="' + module + '_dlg">\n')
    template.write('        <form class="fm" id="' + module + '_fm" method="post" novalidate>\n')

    for row in result:
        field = row['Field']
        field_type = row['Type']
        if not (field in ignored):
            template.write('            <div class="fitem">\n')
            template.write('                <input id="' + module + '_' + field + '" name="' + field + '">\n')
            template.write('            </div>\n')

    template.write('        </form>\n')
    template.write('    </div>\n')
    template.write('</div>\n')
    template.write('<script src="{{ asset(\'assets/js/pages/' + module + '/index.js\') }}"></script>\n')
    template.close()
    print("File created %s" % template_path)

def generate_routes(module):
    routes = open('config/routes.ini', 'a')
    routes.write('\n# ' + module)
    routes_text = f'''
GET /{module} = App\\Controllers\\{module.title()}Controller->index
POST /{module}/get = App\\Controllers\\{module.title()}Controller->get
POST /{module}/add = App\\Controllers\\{module.title()}Controller->insert
POST /{module}/edit/@id = App\\Controllers\\{module.title()}Controller->update
POST /{module}/delete/@id = App\\Controllers\\{module.title()}Controller->delete
    '''
    routes.write(routes_text)
    routes.close()
    print("Routes generated for %s" % module)
    print(routes_text)

if __name__ == "__main__":
    main(sys.argv[1:])
