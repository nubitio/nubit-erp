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

/* layout/base.html.twig */
class __TwigTemplate_5ec3027bff851f654d19f54ec0847d9f extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'title' => [$this, 'block_title'],
            'stylesheets' => [$this, 'block_stylesheets'],
            'javascripts' => [$this, 'block_javascripts'],
            'body' => [$this, 'block_body'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"utf-8\">
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>";
        // line 7
        $this->displayBlock('title', $context, $blocks);
        echo "</title>
    <!-- Icons -->
    <link rel=\"shortcut icon\" href=\"";
        // line 9
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/logo.png"), "html", null, true);
        echo "\">
    <link href=\"https://fonts.googleapis.com/css?family=Ubuntu&display=swap\" rel=\"stylesheet\">
    ";
        // line 11
        $this->displayBlock('stylesheets', $context, $blocks);
        // line 15
        echo "    ";
        $this->displayBlock('javascripts', $context, $blocks);
        // line 26
        echo "</head>
<body class=\"easyui-layout\" id=\"layout-container\">
<div data-options=\"region: 'north', border: false\" class=\"primary-dark\" style=\"height: 56px;\">
    <div class=\"float-start\">
        <a id=\"menu-toggle\" class=\"menu-bars\">
            <i class=\"fa fa-bars fa-lg\"></i>
        </a>
        <a href=\"/\"><img style=\"height:56px;\" src=\"";
        // line 33
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/logo.png"), "html", null, true);
        echo "\" alt=\"logo\"></a>
        <span id=\"home_local_title\" style=\"margin-left: 20px;\"><i
                    class=\"fa fa-home\"></i> ";
        // line 35
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 35), "get", [0 => "local"], "method", true, true, false, 35)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 35), "get", [0 => "local"], "method", false, false, false, 35), "")) : ("")), "html", null, true);
        echo "</span>
    </div>
    <div class=\"float-end\">
        <button class=\"btn md-btn-plain\" onclick=\"window.ThemeManager.toggle()\">
            <i id=\"theme-icon\" class=\"fa fa-sun\"></i>
        </button>
        <img src=\"\" alt=\"\" style=\"height: 56px\">
        <a href=\"#\" class=\"easyui-menubutton menu-user\"
           data-options=\"menu:'#user_menu',iconCls:'fas fa-user fa-1x', showEvent:'click'\">";
        // line 43
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "user", [], "any", false, true, false, 43), "userIdentifier", [], "any", true, true, false, 43)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "user", [], "any", false, true, false, 43), "userIdentifier", [], "any", false, false, false, 43), "")) : ("")), "html", null, true);
        echo "</a>
        <div id=\"user_menu\" style=\"width:165px;\">
            <div data-options=\"iconCls:'fa fa-home fa-fw'\" onclick=\"window.changeLocal()\">
                Cambiar Local
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-lock fa-fw'\" onclick=\"\">
                Cambiar Contraseña
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-power-off fa-fw'\" onclick=\"window.location = ('logout')\">
                Salir
            </div>
        </div>
    </div>
</div>
<div data-options=\"region:'west', border: true\" style=\"width: 252px;\">
    <div id=\"sidebar\" style=\"min-height: 100vh;\"></div>
</div>
<div data-options=\"region:'center', border: true\" id=\"main-content\">
    ";
        // line 63
        $this->displayBlock('body', $context, $blocks);
        // line 65
        echo "</div>
<script>
    window.userRoleId = ";
        // line 67
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "user", [], "any", false, true, false, 67), "rol_id", [], "any", true, true, false, 67)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "user", [], "any", false, true, false, 67), "rol_id", [], "any", false, false, false, 67), 0)) : (0)), "html", null, true);
        echo ";
    window.addEventListener('load', function () {
        let home = new window.app.home();
        \$('#wrapper').animate({\"opacity\": 1});
        \$('.wrapper').animate({\"opacity\": 1});

        window.changeLocal = function () {
            home.changeLocal();
        };
    });
</script>
</body>
</html>";
    }

    // line 7
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 11
    public function block_stylesheets($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 12
        echo "        <link rel=\"stylesheet\" href=\"";
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/css/lightbox.min.css"), "html", null, true);
        echo "\">
        ";
        // line 13
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackLinkTags("main");
        echo "
    ";
    }

    // line 15
    public function block_javascripts($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 16
        echo "        <script src=\"";
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/jquery.min.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 17
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/jquery.easyui.min.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 18
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/easyui-lang-es.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 19
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/datagrid-detailview.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 20
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/datagrid-filter.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 21
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/lightbox.min.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 22
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/config.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 23
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/main.js"), "html", null, true);
        echo "\"></script>
        ";
        // line 24
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackScriptTags("main");
        echo "
    ";
    }

    // line 63
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 64
        echo "    ";
    }

    public function getTemplateName()
    {
        return "layout/base.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  203 => 64,  199 => 63,  193 => 24,  189 => 23,  185 => 22,  181 => 21,  177 => 20,  173 => 19,  169 => 18,  165 => 17,  160 => 16,  156 => 15,  150 => 13,  145 => 12,  141 => 11,  135 => 7,  118 => 67,  114 => 65,  112 => 63,  89 => 43,  78 => 35,  73 => 33,  64 => 26,  61 => 15,  59 => 11,  54 => 9,  49 => 7,  41 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"utf-8\">
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>{% block title %}{% endblock %}</title>
    <!-- Icons -->
    <link rel=\"shortcut icon\" href=\"{{ asset('assets/images/logo.png') }}\">
    <link href=\"https://fonts.googleapis.com/css?family=Ubuntu&display=swap\" rel=\"stylesheet\">
    {% block stylesheets %}
        <link rel=\"stylesheet\" href=\"{{ asset('assets/css/lightbox.min.css') }}\">
        {{ encore_entry_link_tags('main') }}
    {% endblock stylesheets %}
    {% block javascripts %}
        <script src=\"{{ asset('assets/js/jquery.min.js') }}\"></script>
        <script src=\"{{ asset('assets/js/jquery.easyui.min.js') }}\"></script>
        <script src=\"{{ asset('assets/js/easyui-lang-es.js') }}\"></script>
        <script src=\"{{ asset('assets/js/datagrid-detailview.js') }}\"></script>
        <script src=\"{{ asset('assets/js/datagrid-filter.js') }}\"></script>
        <script src=\"{{ asset('assets/js/lightbox.min.js') }}\"></script>
        <script src=\"{{ asset('assets/js/config.js') }}\"></script>
        <script src=\"{{ asset('assets/js/main.js') }}\"></script>
        {{ encore_entry_script_tags('main') }}
    {% endblock javascripts %}
</head>
<body class=\"easyui-layout\" id=\"layout-container\">
<div data-options=\"region: 'north', border: false\" class=\"primary-dark\" style=\"height: 56px;\">
    <div class=\"float-start\">
        <a id=\"menu-toggle\" class=\"menu-bars\">
            <i class=\"fa fa-bars fa-lg\"></i>
        </a>
        <a href=\"/\"><img style=\"height:56px;\" src=\"{{ asset('assets/images/logo.png') }}\" alt=\"logo\"></a>
        <span id=\"home_local_title\" style=\"margin-left: 20px;\"><i
                    class=\"fa fa-home\"></i> {{ app.session.get('local')|default('') }}</span>
    </div>
    <div class=\"float-end\">
        <button class=\"btn md-btn-plain\" onclick=\"window.ThemeManager.toggle()\">
            <i id=\"theme-icon\" class=\"fa fa-sun\"></i>
        </button>
        <img src=\"\" alt=\"\" style=\"height: 56px\">
        <a href=\"#\" class=\"easyui-menubutton menu-user\"
           data-options=\"menu:'#user_menu',iconCls:'fas fa-user fa-1x', showEvent:'click'\">{{ app.user.userIdentifier|default('') }}</a>
        <div id=\"user_menu\" style=\"width:165px;\">
            <div data-options=\"iconCls:'fa fa-home fa-fw'\" onclick=\"window.changeLocal()\">
                Cambiar Local
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-lock fa-fw'\" onclick=\"\">
                Cambiar Contraseña
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-power-off fa-fw'\" onclick=\"window.location = ('logout')\">
                Salir
            </div>
        </div>
    </div>
</div>
<div data-options=\"region:'west', border: true\" style=\"width: 252px;\">
    <div id=\"sidebar\" style=\"min-height: 100vh;\"></div>
</div>
<div data-options=\"region:'center', border: true\" id=\"main-content\">
    {% block body %}
    {% endblock %}
</div>
<script>
    window.userRoleId = {{ app.user.rol_id|default(0) }};
    window.addEventListener('load', function () {
        let home = new window.app.home();
        \$('#wrapper').animate({\"opacity\": 1});
        \$('.wrapper').animate({\"opacity\": 1});

        window.changeLocal = function () {
            home.changeLocal();
        };
    });
</script>
</body>
</html>", "layout/base.html.twig", "/var/www/html/templates/layout/base.html.twig");
    }
}
