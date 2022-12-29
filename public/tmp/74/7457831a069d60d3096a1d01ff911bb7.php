<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* ingreso/index.html.twig */
class __TwigTemplate_64f05ab8699928bdc86d80ee88447b7a extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'title' => [$this, 'block_title'],
            'body' => [$this, 'block_body'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "layout/base.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->parent = $this->loadTemplate("layout/base.html.twig", "ingreso/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Ingreso";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <table id=\"ingreso_dg\"></table>
        <!-- Dialogo -->
        <div id=\"ingreso_dlg\">
            <div class=\"easyui-layout\" data-options=\"fit:true\">
                <form class=\"fm\" id=\"ingreso_fm\" method=\"post\" novalidate>
                    <div data-options=\"region: 'north', border: false\" style=\"height:245px; padding:10px 30px\">
                        <div class=\"row\">
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_fecha\" name=\"fecha\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"ingreso_almacen\" name=\"almacen_id\">
                                </div>
                            </div>
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_operacion\" name=\"operacion_id\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"ingreso_proveedor\" name=\"proveedor_id\">
                                </div>
                            </div>
                            <div class=\"col-md-12\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_glosa\" name=\"glosa\">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-options=\"region: 'center', border: false\" style=\"padding:0 30px\">
                        <table class=\"table table-sm table-bordered header-fixed\">
                            <thead class=\"thead-dark\">
                            <tr>
                                <th style=\"width: 50%\">Producto</th>
                                <th style=\"width: 15%\">Cant.</th>
                                <th style=\"width: 15%\">
                                    <input type=\"checkbox\" id=\"checkbox_pUnitarioID\" name=\"checkbox_pUnitarioName\"
                                           title=\"Habilitar la casilla para ingresar precios de compra\"
                                           class=\"easyui-tooltip\" checked=\"checked\"> S/. Unit.
                                </th>
                                <th style=\"width: 15%\">Total</th>
                                <th style=\"width: 5%\">
                                    <a onclick=\"app.ingreso.add_detalle()\" style=\"cursor: pointer\">
                                        <i class=\"fas fa-plus text-success\" style=\"padding: 3px\"></i>
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody id=\"ingreso_tb_detalle\" style=\"height: 110px\"></tbody>
                            <tfoot>
                            <tr class=\"table-secondary\">
                                <td colspan=\"3\" class=\"text-right\" style=\"width: 80%\"><b>Total</b></td>
                                <td colspan=\"2\" class=\"text-center\" style=\"width: 20%\">
                                    <b>S/ <span id=\"ingreso_total\">0.00</span></b>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script id=\"ingreso_template\" type=\"text/template\">
        <tr id=\"ingreso_tr_%position%\">
            <td style=\"width: 50%\">
                <input id=\"ingreso_cbproducto_%position%\" name=\"detalle[producto][]\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbcantidad_%position%\" name=\"detalle[cantidad][]\" class=\"cantidad\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbprecio_%position%\" name=\"detalle[unitario][]\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbtotal_%position%\" name=\"detalle[total][]\" class=\"ingreso_subtotal\">
            </td>
            <td style=\"width: 5%\">
                <a onclick=\"app.ingreso.remove_row(%position%)\" style=\"cursor: pointer\">
                    <i class=\"fas fa-times text-danger\" style=\"padding:6px\"></i>
                </a>
            </td>
        </tr>
    </script>
    <script src=\"";
        // line 90
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/ingreso/index.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "ingreso/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  146 => 90,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Ingreso{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <table id=\"ingreso_dg\"></table>
        <!-- Dialogo -->
        <div id=\"ingreso_dlg\">
            <div class=\"easyui-layout\" data-options=\"fit:true\">
                <form class=\"fm\" id=\"ingreso_fm\" method=\"post\" novalidate>
                    <div data-options=\"region: 'north', border: false\" style=\"height:245px; padding:10px 30px\">
                        <div class=\"row\">
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_fecha\" name=\"fecha\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"ingreso_almacen\" name=\"almacen_id\">
                                </div>
                            </div>
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_operacion\" name=\"operacion_id\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"ingreso_proveedor\" name=\"proveedor_id\">
                                </div>
                            </div>
                            <div class=\"col-md-12\">
                                <div class=\"fitem\">
                                    <input id=\"ingreso_glosa\" name=\"glosa\">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-options=\"region: 'center', border: false\" style=\"padding:0 30px\">
                        <table class=\"table table-sm table-bordered header-fixed\">
                            <thead class=\"thead-dark\">
                            <tr>
                                <th style=\"width: 50%\">Producto</th>
                                <th style=\"width: 15%\">Cant.</th>
                                <th style=\"width: 15%\">
                                    <input type=\"checkbox\" id=\"checkbox_pUnitarioID\" name=\"checkbox_pUnitarioName\"
                                           title=\"Habilitar la casilla para ingresar precios de compra\"
                                           class=\"easyui-tooltip\" checked=\"checked\"> S/. Unit.
                                </th>
                                <th style=\"width: 15%\">Total</th>
                                <th style=\"width: 5%\">
                                    <a onclick=\"app.ingreso.add_detalle()\" style=\"cursor: pointer\">
                                        <i class=\"fas fa-plus text-success\" style=\"padding: 3px\"></i>
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody id=\"ingreso_tb_detalle\" style=\"height: 110px\"></tbody>
                            <tfoot>
                            <tr class=\"table-secondary\">
                                <td colspan=\"3\" class=\"text-right\" style=\"width: 80%\"><b>Total</b></td>
                                <td colspan=\"2\" class=\"text-center\" style=\"width: 20%\">
                                    <b>S/ <span id=\"ingreso_total\">0.00</span></b>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script id=\"ingreso_template\" type=\"text/template\">
        <tr id=\"ingreso_tr_%position%\">
            <td style=\"width: 50%\">
                <input id=\"ingreso_cbproducto_%position%\" name=\"detalle[producto][]\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbcantidad_%position%\" name=\"detalle[cantidad][]\" class=\"cantidad\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbprecio_%position%\" name=\"detalle[unitario][]\">
            </td>
            <td style=\"width: 15%\">
                <input id=\"ingreso_tbtotal_%position%\" name=\"detalle[total][]\" class=\"ingreso_subtotal\">
            </td>
            <td style=\"width: 5%\">
                <a onclick=\"app.ingreso.remove_row(%position%)\" style=\"cursor: pointer\">
                    <i class=\"fas fa-times text-danger\" style=\"padding:6px\"></i>
                </a>
            </td>
        </tr>
    </script>
    <script src=\"{{ asset('assets/js/pages/ingreso/index.js') }}\"></script>
{% endblock %}
", "ingreso/index.html.twig", "/var/www/html/templates/ingreso/index.html.twig");
    }
}
