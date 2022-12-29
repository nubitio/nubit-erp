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

/* nota/index.html.twig */
class __TwigTemplate_2b31d78564aed82d40e4ecaadca4a033 extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "nota/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Nota";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <table id=\"nota_dg\"></table>
        <!-- Dialogo -->
        <div id=\"nota_dlg\">
            <form class=\"fm\" id=\"nota_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"nota_movimiento_id\" name=\"movimiento_id\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_fecha\" name=\"fecha\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_codigo_motivo\" name=\"codigo_motivo\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_descripcion_motivo\" name=\"descripcion_motivo\">
                </div>
            </form>
        </div>
        <!-- summary-->
        <div id=\"dlgSummary\">
            <table id=\"dgSummary\"></table>
        </div>
    </div>
    <script src=\"";
        // line 28
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/nota/index.js"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 29
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/contabilidad/facturacion/summary.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "nota/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  88 => 29,  84 => 28,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Nota{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <table id=\"nota_dg\"></table>
        <!-- Dialogo -->
        <div id=\"nota_dlg\">
            <form class=\"fm\" id=\"nota_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"nota_movimiento_id\" name=\"movimiento_id\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_fecha\" name=\"fecha\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_codigo_motivo\" name=\"codigo_motivo\">
                </div>
                <div class=\"fitem\">
                    <input id=\"nota_descripcion_motivo\" name=\"descripcion_motivo\">
                </div>
            </form>
        </div>
        <!-- summary-->
        <div id=\"dlgSummary\">
            <table id=\"dgSummary\"></table>
        </div>
    </div>
    <script src=\"{{ asset('assets/js/pages/nota/index.js') }}\"></script>
    <script src=\"{{ asset('assets/js/pages/contabilidad/facturacion/summary.js') }}\"></script>
{% endblock %}
", "nota/index.html.twig", "/var/www/html/templates/nota/index.html.twig");
    }
}
