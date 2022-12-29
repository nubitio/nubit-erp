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

/* dashboard/index.html.twig */
class __TwigTemplate_446fb6398c39330759360604cb20c2fb extends Template
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
        $this->parent = $this->loadTemplate("layout/base.html.twig", "dashboard/index.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Dashboard";
    }

    // line 3
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 4
        echo "    <div class=\"wrapper\">
        <div class=\"container-fluid\">
            <div class=\"row\" style=\"margin-top: 15px\">
                <div class=\"col-md-12 mb-3\" style=\"min-height: 425px\">
                    <div id=\"rotation-panel\">
                        <div class=\"card\">
                            <div class=\"card-header\" style=\"padding: 8px 10px\">
                                <div class=\"row\">
                                    <div class=\"col-md-6\">
                                        <h6>Rotación de productos</h6>
                                    </div>
                                    <div class=\"col-md-6 text-end\">
                                        <input id=\"lapse-selector\">
                                    </div>
                                </div>
                            </div>
                            <div class=\"card-body\" style=\"padding: 8px\">
                                <div class=\"row\">
                                    <div class=\"col-md-4\">
                                        <div class=\"card-panel\" id=\"most-sold\" style=\"min-height: 350px\"></div>
                                    </div>
                                    <div class=\"col-md-4\">
                                        <div class=\"card-panel\" id=\"less-sold\" style=\"min-height: 350px\"></div>
                                    </div>
                                    <div class=\"col-md-4\">
                                        <table id=\"without-rotation\"></table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_semana\" style=\"min-width:320px; height:340px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_mes\" style=\"min-width:320px; height:340px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_dia\" style=\"min-width:320px; height:400px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"monthly_invoicing\"
                         style=\"min-width:320px; height:400px;\"></div>
                </div>
            </div>
        </div>
    </div>
    <script src=\"";
        // line 52
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/dashboard/index.js"), "html", null, true);
        echo "\" defer></script>
";
    }

    public function getTemplateName()
    {
        return "dashboard/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  108 => 52,  58 => 4,  54 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'layout/base.html.twig' %}
{% block title %}Dashboard{% endblock %}
{% block body %}
    <div class=\"wrapper\">
        <div class=\"container-fluid\">
            <div class=\"row\" style=\"margin-top: 15px\">
                <div class=\"col-md-12 mb-3\" style=\"min-height: 425px\">
                    <div id=\"rotation-panel\">
                        <div class=\"card\">
                            <div class=\"card-header\" style=\"padding: 8px 10px\">
                                <div class=\"row\">
                                    <div class=\"col-md-6\">
                                        <h6>Rotación de productos</h6>
                                    </div>
                                    <div class=\"col-md-6 text-end\">
                                        <input id=\"lapse-selector\">
                                    </div>
                                </div>
                            </div>
                            <div class=\"card-body\" style=\"padding: 8px\">
                                <div class=\"row\">
                                    <div class=\"col-md-4\">
                                        <div class=\"card-panel\" id=\"most-sold\" style=\"min-height: 350px\"></div>
                                    </div>
                                    <div class=\"col-md-4\">
                                        <div class=\"card-panel\" id=\"less-sold\" style=\"min-height: 350px\"></div>
                                    </div>
                                    <div class=\"col-md-4\">
                                        <table id=\"without-rotation\"></table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_semana\" style=\"min-width:320px; height:340px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_mes\" style=\"min-width:320px; height:340px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"venta_dia\" style=\"min-width:320px; height:400px;\"></div>
                </div>
                <div class=\"col-md-6\">
                    <div class=\"card-panel chart-box\" id=\"monthly_invoicing\"
                         style=\"min-width:320px; height:400px;\"></div>
                </div>
            </div>
        </div>
    </div>
    <script src=\"{{ asset('assets/js/pages/dashboard/index.js') }}\" defer></script>
{% endblock %}
", "dashboard/index.html.twig", "/var/www/html/templates/dashboard/index.html.twig");
    }
}
