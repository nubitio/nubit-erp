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

/* reportes/invoice.html.twig */
class __TwigTemplate_a7b14bac7a1398c4ab78f736471e349b extends Template
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
    <!-- Required meta tags -->
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">

    <!-- Bootstrap CSS -->
    <link rel=\"stylesheet\" href=\"https://stackpath.bootstrapcdn.com/bootstrap/4.1.2/css/bootstrap.min.css\"
          integrity=\"sha384-Smlep5jCw/wG7hdkwQ/Z5nLIefveQRIY9nfy6xoR1uRYBtpZgI6339F5dgvm/e9B\" crossorigin=\"anonymous\">

    <title>";
        // line 12
        echo twig_escape_filter($this->env, ($context["title"] ?? null), "html", null, true);
        echo "</title>
</head>
<body>
<div class=\"offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12 padding\">
    <div class=\"p-4\">
        <table class=\"table table-borderless\">
            <tr>
                <td class=\"text-center\" style=\"width: 50%\">
                    <img src=\"";
        // line 20
        echo twig_escape_filter($this->env, ($context["logo"] ?? null), "html", null, true);
        echo "\" alt=\"Logo\" style=\"position: relative; max-height: 120px\">
                </td>
                <td>
                    <div class=\"card\">
                        <div class=\"card-body text-center\">
                            <p>RUC: ";
        // line 25
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "ruc", [], "any", false, false, false, 25), "html", null, true);
        echo "</p>
                            <h5>NOTA DE PEDIDO</h5>
                            ";
        // line 27
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "serie", [], "any", false, false, false, 27), "html", null, true);
        echo " - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "numero", [], "any", false, false, false, 27), "html", null, true);
        echo "
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        <hr>
        <table class=\"table table-borderless\">
            <tr>
                <td class=\"text-left\">
                    <p>Establecimiento: ";
        // line 37
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "local", [], "any", false, false, false, 37), "html", null, true);
        echo "</p>
                </td>
                <td>
                    <p>Atentido por: <span class=\"text-uppercase\">";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "username", [], "any", false, false, false, 40), "html", null, true);
        echo "</span></p>
                </td>
            </tr>
            <tr>
                <td>
                    <p>Fecha: ";
        // line 45
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "fecha", [], "any", false, false, false, 45), "html", null, true);
        echo "</p>
                </td>
                <td>
                    <p>Hora: ";
        // line 48
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "date_created", [], "any", false, false, false, 48), "H:m:s"), "html", null, true);
        echo "</p>
                </td>
            </tr>
        </table>
        <table class=\"table table-borderless mb-4\">
            <tr>
                <td style=\"width: 65%\">
                    <div>Nombre de cliente: ";
        // line 55
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "cliente", [], "any", false, false, false, 55), "html", null, true);
        echo "</div>
                </td>
            </tr>
            <tr>
                <td colspan=\"2\">
                    Dirección: ";
        // line 60
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "direccion_cliente", [], "any", false, false, false, 60), "html", null, true);
        echo "
                </td>
            </tr>
        </table>
        <div class=\"table-responsive-sm\">
            <table class=\"table table-striped\">
                <thead>
                <tr>
                    <th class=\"center\">#</th>
                    <th class=\"center\">Cantidad</th>
                    <th>Description</th>
                    <th class=\"right\">Precio</th>
                    <th class=\"right\">Total</th>
                </tr>
                </thead>
                <tbody>
                ";
        // line 76
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["detail"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["item"]) {
            // line 77
            echo "                    <tr>
                        <td class=\"center\">";
            // line 78
            echo twig_escape_filter($this->env, ($context["counter"] ?? null), "html", null, true);
            echo "</td>
                        <td class=\"center\">";
            // line 79
            echo twig_escape_filter($this->env, (($__internal_compile_0 = $context["item"]) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0["cantidad"] ?? null) : null), "html", null, true);
            echo "</td>
                        <td class=\"left\">";
            // line 80
            echo twig_escape_filter($this->env, (($__internal_compile_1 = $context["item"]) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1["producto"] ?? null) : null), "html", null, true);
            echo "</td>
                        <td class=\"right\">";
            // line 81
            echo twig_escape_filter($this->env, (($__internal_compile_2 = $context["item"]) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2["unitario"] ?? null) : null), "html", null, true);
            echo "</td>
                        <td class=\"right\">";
            // line 82
            echo twig_escape_filter($this->env, (($__internal_compile_3 = $context["item"]) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3["precio"] ?? null) : null), "html", null, true);
            echo "</td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['item'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 85
        echo "                </tbody>
            </table>
        </div>
        <br><br>
        <div class=\"row\">
            <div class=\"col-lg-4 col-sm-5\">
            </div>
            <div class=\"col-lg-4 col-sm-5 ml-auto\">
                <table class=\"table table-clear\">
                    <tbody>
                    ";
        // line 95
        if ((twig_get_attribute($this->env, $this->source, ($context["header"] ?? null), "descuento", [], "any", false, false, false, 95) > 0)) {
            // line 96
            echo "                        <tr>
                            <td class=\"left\">Dscto.</td>
                            <td class=\"right\">-";
            // line 98
            echo twig_escape_filter($this->env, ($context["valor_descuento"] ?? null), "html", null, true);
            echo "</td>
                        </tr>
                    ";
        }
        // line 101
        echo "                    <tr>
                        <td class=\"left\">
                            <strong class=\"text-dark\">Total</strong></td>
                        <td class=\"right\">
                            <strong class=\"text-dark\">";
        // line 105
        echo twig_escape_filter($this->env, ($context["total"] ?? null), "html", null, true);
        echo "</strong>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <hr>
        <p class=\"mb-0\">";
        // line 113
        echo twig_escape_filter($this->env, ($context["obs"] ?? null), "html", null, true);
        echo "</p>
    </div>
</div>
</body>
</html>";
    }

    public function getTemplateName()
    {
        return "reportes/invoice.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  219 => 113,  208 => 105,  202 => 101,  196 => 98,  192 => 96,  190 => 95,  178 => 85,  169 => 82,  165 => 81,  161 => 80,  157 => 79,  153 => 78,  150 => 77,  146 => 76,  127 => 60,  119 => 55,  109 => 48,  103 => 45,  95 => 40,  89 => 37,  74 => 27,  69 => 25,  61 => 20,  50 => 12,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html>
<html lang=\"es\">
<head>
    <!-- Required meta tags -->
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">

    <!-- Bootstrap CSS -->
    <link rel=\"stylesheet\" href=\"https://stackpath.bootstrapcdn.com/bootstrap/4.1.2/css/bootstrap.min.css\"
          integrity=\"sha384-Smlep5jCw/wG7hdkwQ/Z5nLIefveQRIY9nfy6xoR1uRYBtpZgI6339F5dgvm/e9B\" crossorigin=\"anonymous\">

    <title>{{ title }}</title>
</head>
<body>
<div class=\"offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12 padding\">
    <div class=\"p-4\">
        <table class=\"table table-borderless\">
            <tr>
                <td class=\"text-center\" style=\"width: 50%\">
                    <img src=\"{{ logo }}\" alt=\"Logo\" style=\"position: relative; max-height: 120px\">
                </td>
                <td>
                    <div class=\"card\">
                        <div class=\"card-body text-center\">
                            <p>RUC: {{ header.ruc }}</p>
                            <h5>NOTA DE PEDIDO</h5>
                            {{ header.serie }} - {{ header.numero }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        <hr>
        <table class=\"table table-borderless\">
            <tr>
                <td class=\"text-left\">
                    <p>Establecimiento: {{ header.local }}</p>
                </td>
                <td>
                    <p>Atentido por: <span class=\"text-uppercase\">{{ header.username }}</span></p>
                </td>
            </tr>
            <tr>
                <td>
                    <p>Fecha: {{ header.fecha }}</p>
                </td>
                <td>
                    <p>Hora: {{ header.date_created|date(\"H:m:s\") }}</p>
                </td>
            </tr>
        </table>
        <table class=\"table table-borderless mb-4\">
            <tr>
                <td style=\"width: 65%\">
                    <div>Nombre de cliente: {{ header.cliente }}</div>
                </td>
            </tr>
            <tr>
                <td colspan=\"2\">
                    Dirección: {{ header.direccion_cliente }}
                </td>
            </tr>
        </table>
        <div class=\"table-responsive-sm\">
            <table class=\"table table-striped\">
                <thead>
                <tr>
                    <th class=\"center\">#</th>
                    <th class=\"center\">Cantidad</th>
                    <th>Description</th>
                    <th class=\"right\">Precio</th>
                    <th class=\"right\">Total</th>
                </tr>
                </thead>
                <tbody>
                {% for item in detail %}
                    <tr>
                        <td class=\"center\">{{ counter }}</td>
                        <td class=\"center\">{{ item['cantidad'] }}</td>
                        <td class=\"left\">{{ item['producto'] }}</td>
                        <td class=\"right\">{{ item['unitario'] }}</td>
                        <td class=\"right\">{{ item['precio'] }}</td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
        </div>
        <br><br>
        <div class=\"row\">
            <div class=\"col-lg-4 col-sm-5\">
            </div>
            <div class=\"col-lg-4 col-sm-5 ml-auto\">
                <table class=\"table table-clear\">
                    <tbody>
                    {% if header.descuento > 0 %}
                        <tr>
                            <td class=\"left\">Dscto.</td>
                            <td class=\"right\">-{{ valor_descuento }}</td>
                        </tr>
                    {% endif %}
                    <tr>
                        <td class=\"left\">
                            <strong class=\"text-dark\">Total</strong></td>
                        <td class=\"right\">
                            <strong class=\"text-dark\">{{ total }}</strong>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <hr>
        <p class=\"mb-0\">{{ obs }}</p>
    </div>
</div>
</body>
</html>", "reportes/invoice.html.twig", "/var/www/html/templates/reportes/invoice.html.twig");
    }
}
