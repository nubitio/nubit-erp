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

/* salida/index.html.twig */
class __TwigTemplate_90b5e82b6849131472e398e9a0144125 extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "salida/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Salida";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <table id=\"salida_dg\"></table>
        <!-- Dialogo -->
        <div id=\"salida_dlg\">
            <div class=\"easyui-layout\" data-options=\"fit:true\">
                <form class=\"fm\" id=\"salida_fm\" method=\"post\" novalidate>
                    <div data-options=\"region: 'north', border: false\" style=\"height:185px; padding:10px 30px\">
                        <div class=\"row\">
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"salida_fecha\" name=\"fecha\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"salida_almacen\" name=\"almacen_id\">
                                </div>
                            </div>
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"salida_operacion\" name=\"operacion_id\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"salida_glosa\" name=\"glosa\">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-options=\"region: 'center', border: false\" style=\"padding:0 30px\">
                        <table class=\"table table-sm table-bordered header-fixed\">
                            <thead class=\"thead-dark\">
                            <tr>
                                <th style=\"width: 74%\">Producto</th>
                                <th style=\"width: 20%\">Cantidad</th>
                                <th style=\"width: 6%\">
                                    <a onclick=\"app.salida.add_detalle()\" style=\"cursor: pointer\">
                                        <i class=\"fas fa-plus text-success\" style=\"padding: 3px\"></i>
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody id=\"salida_tb_detalle\" style=\"height: 110px\"></tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script id=\"salida_template\" type=\"text/template\">
        <tr id=\"salida_tr_%position%\">
            <td style=\"width: 74%\">
                <input id=\"salida_cbproducto_%position%\" name=\"detalle[producto][]\">
            </td>
            <td style=\"width: 20%\">
                <input id=\"salida_tbcantidad_%position%\" name=\"detalle[cantidad][]\" class=\"cantidad\">
            </td>
            <td style=\"width: 6%\">
                <a href=\"#\" onclick=\"app.salida.remove_row(%position%)\">
                    <i class=\"fas fa-times text-danger\" style=\"padding: 6px\"></i>
                </a>
            </td>
        </tr>
    </script>
    <script src=\"";
        // line 65
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/salida/index.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "salida/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  121 => 65,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Salida{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <table id=\"salida_dg\"></table>
        <!-- Dialogo -->
        <div id=\"salida_dlg\">
            <div class=\"easyui-layout\" data-options=\"fit:true\">
                <form class=\"fm\" id=\"salida_fm\" method=\"post\" novalidate>
                    <div data-options=\"region: 'north', border: false\" style=\"height:185px; padding:10px 30px\">
                        <div class=\"row\">
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"salida_fecha\" name=\"fecha\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"salida_almacen\" name=\"almacen_id\">
                                </div>
                            </div>
                            <div class=\"col-md-6\">
                                <div class=\"fitem\">
                                    <input id=\"salida_operacion\" name=\"operacion_id\">
                                </div>
                                <div class=\"fitem\">
                                    <input id=\"salida_glosa\" name=\"glosa\">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-options=\"region: 'center', border: false\" style=\"padding:0 30px\">
                        <table class=\"table table-sm table-bordered header-fixed\">
                            <thead class=\"thead-dark\">
                            <tr>
                                <th style=\"width: 74%\">Producto</th>
                                <th style=\"width: 20%\">Cantidad</th>
                                <th style=\"width: 6%\">
                                    <a onclick=\"app.salida.add_detalle()\" style=\"cursor: pointer\">
                                        <i class=\"fas fa-plus text-success\" style=\"padding: 3px\"></i>
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody id=\"salida_tb_detalle\" style=\"height: 110px\"></tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script id=\"salida_template\" type=\"text/template\">
        <tr id=\"salida_tr_%position%\">
            <td style=\"width: 74%\">
                <input id=\"salida_cbproducto_%position%\" name=\"detalle[producto][]\">
            </td>
            <td style=\"width: 20%\">
                <input id=\"salida_tbcantidad_%position%\" name=\"detalle[cantidad][]\" class=\"cantidad\">
            </td>
            <td style=\"width: 6%\">
                <a href=\"#\" onclick=\"app.salida.remove_row(%position%)\">
                    <i class=\"fas fa-times text-danger\" style=\"padding: 6px\"></i>
                </a>
            </td>
        </tr>
    </script>
    <script src=\"{{ asset('assets/js/pages/salida/index.js') }}\"></script>
{% endblock %}
", "salida/index.html.twig", "/var/www/html/templates/salida/index.html.twig");
    }
}
