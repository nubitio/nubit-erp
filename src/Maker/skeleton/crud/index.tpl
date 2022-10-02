{% extends 'layout/base.html.twig' %}
{% block title %}{{module}}{% endblock %}
{% block body %}
    <div id="wrapper" class="wrapper"></div>
{% endblock %}
{% block javascripts %}
    {{ parent() }}
    <script>
        window.addEventListener('load', function () {
            const {{camelModule}} = new window.app.{{camelModule}}('wrapper');
            {{camelModule}}.render();
        });
    </script>
{% endblock %}