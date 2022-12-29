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

/* movimientos/index.html.twig */
class __TwigTemplate_5f05a00207335720a4c63ba8d8580d68 extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "movimientos/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Movimientos";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <table id=\"movimientos_dg\"></table>
        <div id=\"movimientos_toolbar\" style=\"padding:2px 5px;\">
            <input id=\"movimientos_almacen\">
            <input id=\"movimientos_categoria\">
            <a id=\"movimientos_kardex\">Kardex</a>
            <a id=\"movimientos_reporte\">Reporte</a>
            <div class=\"float-end\">
                <input id=\"movimientos_fi\" name=\"fi\">
                <input id=\"movimientos_ff\" name=\"ff\">
                <a id=\"movimientos_filtrar\">Filtrar</a>
            </div>
            <div class=\"clearfix\"></div>
        </div>
    </div>
    <script src=\"";
        // line 19
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/movimientos/index.js"), "html", null, true);
        echo "\"></script>
";
    }

    public function getTemplateName()
    {
        return "movimientos/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  75 => 19,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Movimientos{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <table id=\"movimientos_dg\"></table>
        <div id=\"movimientos_toolbar\" style=\"padding:2px 5px;\">
            <input id=\"movimientos_almacen\">
            <input id=\"movimientos_categoria\">
            <a id=\"movimientos_kardex\">Kardex</a>
            <a id=\"movimientos_reporte\">Reporte</a>
            <div class=\"float-end\">
                <input id=\"movimientos_fi\" name=\"fi\">
                <input id=\"movimientos_ff\" name=\"ff\">
                <a id=\"movimientos_filtrar\">Filtrar</a>
            </div>
            <div class=\"clearfix\"></div>
        </div>
    </div>
    <script src=\"{{ asset('assets/js/pages/movimientos/index.js') }}\"></script>
{% endblock %}
", "movimientos/index.html.twig", "/var/www/html/templates/movimientos/index.html.twig");
    }
}
