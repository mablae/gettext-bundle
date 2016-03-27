# mablae/gettext-bundle

A parser to extract `{{ __('String to translate') }}` tokens from twig 
files and write them to *.po files.

It comes with a `locale:extract:twig` command that can be called like this:

`php app/console locale:extract:twig output.po src/AppBundle/Resources some/other/path`