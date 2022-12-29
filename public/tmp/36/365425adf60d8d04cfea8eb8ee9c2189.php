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

/* login/index.html.twig */
class __TwigTemplate_03ec0522718597e11e5ad58f1ab7b29c extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
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
        echo twig_escape_filter($this->env, ($context["title"] ?? null), "html", null, true);
        echo "</title>
    <!-- Icons -->
    <link rel=\"shortcut icon\" href=\"";
        // line 9
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/logo.png"), "html", null, true);
        echo "\">
    <link href=\"https://fonts.googleapis.com/css?family=Ubuntu&display=swap\" rel=\"stylesheet\">
    ";
        // line 11
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackLinkTags("main");
        echo "
    <!-- build:js ../assets/js/lib.min.js -->
    <script src=\"";
        // line 13
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/jquery.min.js"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 14
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/jquery.easyui.min.js"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 15
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/config.js"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 16
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/main.js"), "html", null, true);
        echo "\"></script>
    ";
        // line 17
        echo $this->extensions['Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension']->renderWebpackScriptTags("main");
        echo "
    <!-- endbuild -->
</head>
<body class=\"login wrapper\">
<div class=\"container-fluid login-container h-100\">
    <div class=\"row h-100 justify-content-center align-items-center\">
        <div class=\"col-5 h-100 d-none d-sm-block login-art\">
            <div class=\"row h-auto justify-content-center\">
                <div class=\"col-md-8\">
                    <div class=\"mt-4\" style=\"max-width:200px; height:50px; overflow:hidden\">
                        <img src=\"";
        // line 27
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/logo.png"), "html", null, true);
        echo "\" class=\"rounded\" style=\"height: 50px;\">
                    </div>
                </div>
            </div>
            <div class=\"row h-75 justify-content-center align-items-center\">
                <div class=\"col-md-8\">
                    <img src=\"";
        // line 33
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/images/illustration.svg"), "html", null, true);
        echo "\" alt=\"\">
                    <div class=\"mt-5\">
                        <h1>Sistema de ventas Efact</h1>
                    </div>
                    <div class=\"mt-4 lead text-muted\">
                        Gestiona tus ventas en un solo lugar
                    </div>
                    <div class=\"mt-4 text-muted\">v";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["app"] ?? null), "env", [], "any", false, false, false, 40), "APP_VERSION", [], "any", false, false, false, 40), "html", null, true);
        echo "</div>
                </div>
            </div>
        </div>
        <div class=\"col-12 col-sm-7\">
            <div class=\"row justify-content-center\">
                <div class=\"col-12 col-sm-5\">
                    <h1>Iniciar sesión</h1>
                    <p>Por favor ingresa tus datos para iniciar sesión</p>
                    <form id=\"login_fm\" class=\"login-form\" method=\"post\">
                        <div class=\"mb-2\">
                            <input id=\"username\" name=\"username\">
                        </div>
                        <div class=\"mb-4\">
                            <input id=\"password\" name=\"password\">
                        </div>
                        <div class=\"text-center mb-4\">
                            <a id=\"login\" onclick=\"app.login.login()\" class=\"md-btn accent\">Iniciar sesión</a>
                        </div>
                    </form>
                </div>
                <div class=\"col-2 d-none d-sm-block\"></div>
            </div>
        </div>
    </div>
    <div id=\"home_local\">
        <div id=\"home_lista_local\"></div>
    </div>
</div>
<script src=\"";
        // line 69
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("assets/js/pages/login/index.js"), "html", null, true);
        echo "\"></script>
</body>
</html>";
    }

    public function getTemplateName()
    {
        return "login/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  140 => 69,  108 => 40,  98 => 33,  89 => 27,  76 => 17,  72 => 16,  68 => 15,  64 => 14,  60 => 13,  55 => 11,  50 => 9,  45 => 7,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"utf-8\">
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>{{ title }}</title>
    <!-- Icons -->
    <link rel=\"shortcut icon\" href=\"{{ asset('assets/images/logo.png') }}\">
    <link href=\"https://fonts.googleapis.com/css?family=Ubuntu&display=swap\" rel=\"stylesheet\">
    {{ encore_entry_link_tags('main') }}
    <!-- build:js ../assets/js/lib.min.js -->
    <script src=\"{{ asset('assets/js/jquery.min.js') }}\"></script>
    <script src=\"{{ asset('assets/js/jquery.easyui.min.js') }}\"></script>
    <script src=\"{{ asset('assets/js/config.js') }}\"></script>
    <script src=\"{{ asset('assets/js/main.js') }}\"></script>
    {{ encore_entry_script_tags('main') }}
    <!-- endbuild -->
</head>
<body class=\"login wrapper\">
<div class=\"container-fluid login-container h-100\">
    <div class=\"row h-100 justify-content-center align-items-center\">
        <div class=\"col-5 h-100 d-none d-sm-block login-art\">
            <div class=\"row h-auto justify-content-center\">
                <div class=\"col-md-8\">
                    <div class=\"mt-4\" style=\"max-width:200px; height:50px; overflow:hidden\">
                        <img src=\"{{ asset('assets/images/logo.png') }}\" class=\"rounded\" style=\"height: 50px;\">
                    </div>
                </div>
            </div>
            <div class=\"row h-75 justify-content-center align-items-center\">
                <div class=\"col-md-8\">
                    <img src=\"{{ asset('assets/images/illustration.svg') }}\" alt=\"\">
                    <div class=\"mt-5\">
                        <h1>Sistema de ventas Efact</h1>
                    </div>
                    <div class=\"mt-4 lead text-muted\">
                        Gestiona tus ventas en un solo lugar
                    </div>
                    <div class=\"mt-4 text-muted\">v{{ app.env.APP_VERSION }}</div>
                </div>
            </div>
        </div>
        <div class=\"col-12 col-sm-7\">
            <div class=\"row justify-content-center\">
                <div class=\"col-12 col-sm-5\">
                    <h1>Iniciar sesión</h1>
                    <p>Por favor ingresa tus datos para iniciar sesión</p>
                    <form id=\"login_fm\" class=\"login-form\" method=\"post\">
                        <div class=\"mb-2\">
                            <input id=\"username\" name=\"username\">
                        </div>
                        <div class=\"mb-4\">
                            <input id=\"password\" name=\"password\">
                        </div>
                        <div class=\"text-center mb-4\">
                            <a id=\"login\" onclick=\"app.login.login()\" class=\"md-btn accent\">Iniciar sesión</a>
                        </div>
                    </form>
                </div>
                <div class=\"col-2 d-none d-sm-block\"></div>
            </div>
        </div>
    </div>
    <div id=\"home_local\">
        <div id=\"home_lista_local\"></div>
    </div>
</div>
<script src=\"{{ asset('assets/js/pages/login/index.js') }}\"></script>
</body>
</html>", "login/index.html.twig", "/var/www/html/templates/login/index.html.twig");
    }
}
