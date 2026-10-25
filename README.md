# mj-zana/view-twig

Twig templates for [mj-zana/view](https://github.com/mjnable/zana-view).

```
composer require mj-zana/view-twig
```

Register `TwigServiceProvider` together with `ViewServiceProvider`. Templates
then end in `.html.twig`, in the same folders: `templates/` and each module's
`Resources/views/`, under the module's name.

```twig
{% extends 'layouts/site' %}
{% block content %}
  <h1>{{ heading }}</h1>
  <a href="{{ url('contact') }}">{{ 'nav.contact'|t }}</a>
  <form method="post">{{ csrf_field('contact') }}</form>
{% endblock %}
```

- **Names:** the extension is optional: `View::make('pages/about')`,
  `{% include '@Contact/form' %}`.
- **Escaping:** autoescape is `html`. `Markup` values and safe helpers such as
  `csrf_field()` are not escaped.
- **Helpers:** every view helper is a Twig function, and `t` is also a filter.
- **Cache:** in production, compiled templates are cached in `view.cache`
  (default `var/cache/twig`). Elsewhere, templates recompile when they
  change.
- **Twig extensions:** add them in a provider's `boot()` through
  `Twig\Environment`.

## License

MIT
