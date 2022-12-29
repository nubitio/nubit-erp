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

/* reporte/index.html.twig */
class __TwigTemplate_dd3fe001f8c218198c012af8e8233be3 extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "reporte/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Reportes";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <table id=\"reporte_dg\"></table>
        <div id=\"reporte_toolbar\">
            <a href=\"javascript:void(0)\" class=\"easyui-menubutton\"
               data-options=\"menu:'#reporte_reportes',iconCls:'fa fa-1x fa-file', showEvent:'click'\">Reportes</a>
            <div class=\"float-end\">
                <input id=\"reporte_fecha_inicio\">
                <input id=\"reporte_fecha_fin\">
            </div>
            <div class=\"clearfix\"></div>
        </div>
        <div id=\"reporte_reportes\" style=\"width:180px;\">
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_articulo()\">Venta por articulo
            </div>
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_detallado()\"> Venta detallado
            </div>
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_contable()\">Venta contable</div>
            <div data-options=\"iconCls:'fa fa-file color-yellow'\" onclick=\"app.reporte.rpt_ventas_anuladas()\">
                Ventas anuladas
            </div>
            ";
        // line 24
        if ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "get", [0 => "show_airport_report"], "method", false, false, false, 24) == "true")) {
            // line 25
            echo "                <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_aeropuerto()\">
                    Reporte aeropuerto
                </div>
            ";
        }
        // line 29
        echo "            <div data-options=\"iconCls:'fa fa-file color-yellow'\" onclick=\"app.reporte.rpt_notas_de_credito()\">
                Notas de credito
            </div>
        </div>
    </div>
    <script src=\"";
        // line 34
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/reporte/index.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "reporte/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  95 => 34,  88 => 29,  82 => 25,  80 => 24,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Reportes{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <table id=\"reporte_dg\"></table>
        <div id=\"reporte_toolbar\">
            <a href=\"javascript:void(0)\" class=\"easyui-menubutton\"
               data-options=\"menu:'#reporte_reportes',iconCls:'fa fa-1x fa-file', showEvent:'click'\">Reportes</a>
            <div class=\"float-end\">
                <input id=\"reporte_fecha_inicio\">
                <input id=\"reporte_fecha_fin\">
            </div>
            <div class=\"clearfix\"></div>
        </div>
        <div id=\"reporte_reportes\" style=\"width:180px;\">
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_articulo()\">Venta por articulo
            </div>
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_detallado()\"> Venta detallado
            </div>
            <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_contable()\">Venta contable</div>
            <div data-options=\"iconCls:'fa fa-file color-yellow'\" onclick=\"app.reporte.rpt_ventas_anuladas()\">
                Ventas anuladas
            </div>
            {% if settings.get('show_airport_report') == 'true' %}
                <div data-options=\"iconCls:'fa fa-file color-green'\" onclick=\"app.reporte.rpt_aeropuerto()\">
                    Reporte aeropuerto
                </div>
            {% endif %}
            <div data-options=\"iconCls:'fa fa-file color-yellow'\" onclick=\"app.reporte.rpt_notas_de_credito()\">
                Notas de credito
            </div>
        </div>
    </div>
    <script src=\"{{ asset('assets/js/pages/reporte/index.js') }}\"></script>
{% endblock %}
", "reporte/index.html.twig", "/var/www/html/templates/reporte/index.html.twig");
    }
}
