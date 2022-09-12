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
class __TwigTemplate_fd0566329cb7df51978bdcb629b3665f extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "layout/base.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "layout/base.html.twig"));

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
        // line 32
        echo "</head>
<body class=\"easyui-layout\" id=\"layout-container\">
<div data-options=\"region: 'north', border: false\" class=\"primary-dark\" style=\"height: 56px;\">
    <div class=\"float-start\">
        <div class=\"easyui-linkbutton menu-bars\" data-options=\"plain: true\" onclick=\"toggle()\">
            <i class=\"fa fa-bars fa-lg\"></i>
        </div>
        <a href=\"/\"><img style=\"height:56px;\" src=\"";
        // line 39
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/logo.png"), "html", null, true);
        echo "\" alt=\"logo\"></a>
        <span id=\"home_local_title\" style=\"margin-left: 20px;\"><i
                    class=\"fa fa-home\"></i> ";
        // line 41
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 41), "get", [0 => "local"], "method", true, true, false, 41)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 41), "get", [0 => "local"], "method", false, false, false, 41), "")) : ("")), "html", null, true);
        echo "</span>
    </div>
    <div class=\"float-end\">
        <button class=\"btn md-btn-plain\" onclick=\"window.ThemeManager.toggle()\">
            <i id=\"theme-icon\" class=\"fa fa-sun\"></i>
        </button>
        <img src=\"\" alt=\"\" style=\"height: 56px\">
        <a href=\"#\" class=\"easyui-menubutton menu-user\"
           data-options=\"menu:'#user_menu',iconCls:'fas fa-user fa-1x', showEvent:'click'\">";
        // line 49
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 49), "get", [0 => "usuario"], "method", true, true, false, 49)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "session", [], "any", false, true, false, 49), "get", [0 => "usuario"], "method", false, false, false, 49), "")) : ("")), "html", null, true);
        echo "</a>
        <div id=\"user_menu\" style=\"width:165px;\">
            <div data-options=\"iconCls:'fa fa-home fa-fw'\" onclick=\"app.home.change_local()\">Cambiar Local</div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-lock fa-fw'\" onclick=\"loadPage('password', 'Cambiar contraseña')\">Cambiar
                Contraseña
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-power-off fa-fw'\" onclick=\"window.location = ('logout')\">Salir</div>
        </div>
    </div>
</div>
<div data-options=\"region:'west', border: true\" style=\"width: 252px;\">
    <div id=\"sidebar\" style=\"min-height: 100vh;\"></div>
</div>
<div data-options=\"region:'center', border: true\">
    ";
        // line 65
        $this->displayBlock('body', $context, $blocks);
        // line 67
        echo "    <div id=\"home_local\">
        <div id=\"home_lista_local\"></div>
    </div>
</div>
<script>
    let layout = \$('#layout-container');
    let sidebar = \$('#sidebar');
    let themeIcon = \$('#theme-icon');
    let current;

    window.onload = function () {
        \$('.wrapper').animate({\"opacity\": 1});
        \$('#wrapper').animate({\"opacity\": 1});

        let selectedMenu = JSON.parse(localStorage.getItem('selectedMenu'));

        \$.get('getmenu')
            .done(function (response) {
                let menu = []
                response.forEach(item => {
                    if (item.children) {
                        item.children.forEach(child => {
                            child.parent = item.text;
                            child.iconCls = '';
                        })
                    }
                    menu.push(item);
                });

                sidebar.sidemenu({
                    data: menu,
                    fit: true,
                    border: false,
                    multiple: false,
                    onSelect: function (item) {
                        if (selectedMenu.url === item.url) {
                            return;
                        }
                        localStorage.setItem('selectedMenu', JSON.stringify(item));
                        window.location.href = item.url;
                    }
                });

                let sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
                setSidebarCollapsed(sidebarCollapsed === 'true');

                if (selectedMenu !== null) {
                    sidebar.sidemenu('setSelected', selectedMenu);
                }
            })
            .fail(function (xhr, status, error) {
                console.log(error);
            });

        window.Observer.subscribe(window.EventType.THEME_CHANGE, (theme) => {
            if (theme === 'dark') {
                themeIcon.removeClass('fa-moon').addClass('fa-sun');
            } else {
                themeIcon.removeClass('fa-sun').addClass('fa-moon');
            }
        });
    };

    window.onresize = function () {
        layout.layout('resize');
        sidebar.sidemenu('resize');
    };

    function toggle() {
        let opts = sidebar.sidemenu('options');
        setSidebarCollapsed(!opts.collapsed);
        let selectedMenu = JSON.parse(localStorage.getItem('selectedMenu'));
        sidebar.sidemenu('setSelected', selectedMenu);
    }

    function setSidebarCollapsed(collapse) {
        sidebar.sidemenu(collapse ? 'collapse' : 'expand');
        let opts = sidebar.sidemenu('options');
        let west = layout.layout('panel', 'west');
        west.panel('resize', {
            width: opts.collapsed ? 50 : 250
        });
        layout.layout('resize');
        sidebar.sidemenu('resize', {
            width: opts.collapsed ? 50 : 250
        });
        localStorage.setItem('sidebarCollapsed', opts.collapsed);
    }
</script>
<script src=\"";
        // line 156
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/layout/home.js"), "html", null, true);
        echo "\"></script>
</body>
</html>";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    // line 7
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 11
    public function block_stylesheets($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "stylesheets"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "stylesheets"));

        // line 12
        echo "        <link rel=\"stylesheet\" href=\"";
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/css/lightbox.min.css"), "html", null, true);
        echo "\">
        ";
        // line 13
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackLinkTags("main");
        echo "
    ";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 15
    public function block_javascripts($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "javascripts"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "javascripts"));

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
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/highcharts.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 22
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/highcharts-more.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 23
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/dumbbell.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 24
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/lollipop.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 25
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/solid-gauge.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 26
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/exporting.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 27
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/lightbox.min.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 28
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/config.js"), "html", null, true);
        echo "\"></script>
        <script src=\"";
        // line 29
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/main.js"), "html", null, true);
        echo "\"></script>
        ";
        // line 30
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackScriptTags("main");
        echo "
    ";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 65
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        // line 66
        echo "    ";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

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
        return array (  354 => 66,  344 => 65,  332 => 30,  328 => 29,  324 => 28,  320 => 27,  316 => 26,  312 => 25,  308 => 24,  304 => 23,  300 => 22,  296 => 21,  292 => 20,  288 => 19,  284 => 18,  280 => 17,  275 => 16,  265 => 15,  253 => 13,  248 => 12,  238 => 11,  220 => 7,  207 => 156,  116 => 67,  114 => 65,  95 => 49,  84 => 41,  79 => 39,  70 => 32,  67 => 15,  65 => 11,  60 => 9,  55 => 7,  47 => 1,);
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
        <script src=\"{{ asset('assets/js/highcharts.js') }}\"></script>
        <script src=\"{{ asset('assets/js/highcharts-more.js') }}\"></script>
        <script src=\"{{ asset('assets/js/dumbbell.js') }}\"></script>
        <script src=\"{{ asset('assets/js/lollipop.js') }}\"></script>
        <script src=\"{{ asset('assets/js/solid-gauge.js') }}\"></script>
        <script src=\"{{ asset('assets/js/exporting.js') }}\"></script>
        <script src=\"{{ asset('assets/js/lightbox.min.js') }}\"></script>
        <script src=\"{{ asset('assets/js/config.js') }}\"></script>
        <script src=\"{{ asset('assets/js/main.js') }}\"></script>
        {{ encore_entry_script_tags('main') }}
    {% endblock javascripts %}
</head>
<body class=\"easyui-layout\" id=\"layout-container\">
<div data-options=\"region: 'north', border: false\" class=\"primary-dark\" style=\"height: 56px;\">
    <div class=\"float-start\">
        <div class=\"easyui-linkbutton menu-bars\" data-options=\"plain: true\" onclick=\"toggle()\">
            <i class=\"fa fa-bars fa-lg\"></i>
        </div>
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
           data-options=\"menu:'#user_menu',iconCls:'fas fa-user fa-1x', showEvent:'click'\">{{ app.session.get('usuario')|default('') }}</a>
        <div id=\"user_menu\" style=\"width:165px;\">
            <div data-options=\"iconCls:'fa fa-home fa-fw'\" onclick=\"app.home.change_local()\">Cambiar Local</div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-lock fa-fw'\" onclick=\"loadPage('password', 'Cambiar contraseña')\">Cambiar
                Contraseña
            </div>
            <div class=\"menu-sep\"></div>
            <div data-options=\"iconCls:'fa fa-power-off fa-fw'\" onclick=\"window.location = ('logout')\">Salir</div>
        </div>
    </div>
</div>
<div data-options=\"region:'west', border: true\" style=\"width: 252px;\">
    <div id=\"sidebar\" style=\"min-height: 100vh;\"></div>
</div>
<div data-options=\"region:'center', border: true\">
    {% block body %}
    {% endblock %}
    <div id=\"home_local\">
        <div id=\"home_lista_local\"></div>
    </div>
</div>
<script>
    let layout = \$('#layout-container');
    let sidebar = \$('#sidebar');
    let themeIcon = \$('#theme-icon');
    let current;

    window.onload = function () {
        \$('.wrapper').animate({\"opacity\": 1});
        \$('#wrapper').animate({\"opacity\": 1});

        let selectedMenu = JSON.parse(localStorage.getItem('selectedMenu'));

        \$.get('getmenu')
            .done(function (response) {
                let menu = []
                response.forEach(item => {
                    if (item.children) {
                        item.children.forEach(child => {
                            child.parent = item.text;
                            child.iconCls = '';
                        })
                    }
                    menu.push(item);
                });

                sidebar.sidemenu({
                    data: menu,
                    fit: true,
                    border: false,
                    multiple: false,
                    onSelect: function (item) {
                        if (selectedMenu.url === item.url) {
                            return;
                        }
                        localStorage.setItem('selectedMenu', JSON.stringify(item));
                        window.location.href = item.url;
                    }
                });

                let sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
                setSidebarCollapsed(sidebarCollapsed === 'true');

                if (selectedMenu !== null) {
                    sidebar.sidemenu('setSelected', selectedMenu);
                }
            })
            .fail(function (xhr, status, error) {
                console.log(error);
            });

        window.Observer.subscribe(window.EventType.THEME_CHANGE, (theme) => {
            if (theme === 'dark') {
                themeIcon.removeClass('fa-moon').addClass('fa-sun');
            } else {
                themeIcon.removeClass('fa-sun').addClass('fa-moon');
            }
        });
    };

    window.onresize = function () {
        layout.layout('resize');
        sidebar.sidemenu('resize');
    };

    function toggle() {
        let opts = sidebar.sidemenu('options');
        setSidebarCollapsed(!opts.collapsed);
        let selectedMenu = JSON.parse(localStorage.getItem('selectedMenu'));
        sidebar.sidemenu('setSelected', selectedMenu);
    }

    function setSidebarCollapsed(collapse) {
        sidebar.sidemenu(collapse ? 'collapse' : 'expand');
        let opts = sidebar.sidemenu('options');
        let west = layout.layout('panel', 'west');
        west.panel('resize', {
            width: opts.collapsed ? 50 : 250
        });
        layout.layout('resize');
        sidebar.sidemenu('resize', {
            width: opts.collapsed ? 50 : 250
        });
        localStorage.setItem('sidebarCollapsed', opts.collapsed);
    }
</script>
<script src=\"{{ asset('assets/js/pages/layout/home.js') }}\"></script>
</body>
</html>", "layout/base.html.twig", "/var/www/html/templates/layout/base.html.twig");
    }
}
