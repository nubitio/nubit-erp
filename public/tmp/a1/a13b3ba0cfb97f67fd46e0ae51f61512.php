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

/* caja/index.html.twig */
class __TwigTemplate_b95250bc682f900900c7d5a68e87bf58 extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "caja/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Caja";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <form id=\"caja_fm_pr\" method=\"post\" novalidate style=\"visibility: hidden\">
            <input type=\"hidden\" name=\"caja_id\" id=\"caja_id\">
        </form>
        <table id=\"caja_dg\" data-options=\"border:false\"></table>
        <!-- Dialogo -->
        <div id=\"caja_dlg\">
            <form class=\"fm\" id=\"caja_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"caja_tipo\" name=\"tipo_id\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_descripcion\" name=\"descripcion\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_referencia\" name=\"referencia\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_monto\" name=\"monto\">
                </div>
            </form>
        </div>
        <!--Dialogo Abrir caja-->
        <div id=\"abrir_dlg\">
            <form class=\"fm\" id=\"abrir_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"saldo_inicial\" name=\"saldo_inicial\">
                </div>
            </form>
        </div>
    </div>
    <script src=\"";
        // line 35
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/caja/index.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "caja/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  91 => 35,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Caja{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <form id=\"caja_fm_pr\" method=\"post\" novalidate style=\"visibility: hidden\">
            <input type=\"hidden\" name=\"caja_id\" id=\"caja_id\">
        </form>
        <table id=\"caja_dg\" data-options=\"border:false\"></table>
        <!-- Dialogo -->
        <div id=\"caja_dlg\">
            <form class=\"fm\" id=\"caja_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"caja_tipo\" name=\"tipo_id\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_descripcion\" name=\"descripcion\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_referencia\" name=\"referencia\">
                </div>
                <div class=\"fitem\">
                    <input id=\"caja_monto\" name=\"monto\">
                </div>
            </form>
        </div>
        <!--Dialogo Abrir caja-->
        <div id=\"abrir_dlg\">
            <form class=\"fm\" id=\"abrir_fm\" method=\"post\" novalidate>
                <div class=\"fitem\">
                    <input id=\"saldo_inicial\" name=\"saldo_inicial\">
                </div>
            </form>
        </div>
    </div>
    <script src=\"{{ asset('assets/js/pages/caja/index.js') }}\"></script>
{% endblock %}
", "caja/index.html.twig", "/var/www/html/templates/caja/index.html.twig");
    }
}
