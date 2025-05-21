LaravelCollective Form To Raw Html
==================================

Provides Artisan command to replace LaravelCollective `Form::` syntax by raw HTML

It searches for `{!! Form::<method>(<arguments>) !!}` or `{{ Form::<method>(<arguments>) }}`,
then analyzes arguments to determine the HTML tag attributes.

* [Installation](#installation)
* [Usage](#usage)


Installation
------------

With Composer, as dev dependency:

```sh
composer require axn/laravelcollective-form-to-raw-html --dev
```

Usage
-----

Simply run this command:

```sh
php artisan laravelcollective-form-to-raw-html:run
```

By default, the command scans all files in `resources/views/`.

You can precise an other directory:

```sh
php artisan laravelcollective-form-to-raw-html:run resources/views/admin/users
```

Or a single file:

```sh
php artisan laravelcollective-form-to-raw-html:run resources/views/admin/users/create.blade.php
```

**NOTE:** The target path is always relative to the project root.

The supported methods are:

* open(options)
* close()
* label(name, value, options, escape)
* labelRequired(name, value, options, escape)
* input(type, name, value, options)
* text(name, value, options)
* number(name, value, options)
* date(name, value, options)
* time(name, value, options)
* datetime(name, value, options)
* week(name, value, options)
* month(name, value, options)
* range(name, value, options)
* search(name, value, options)
* email(name, value, options)
* tel(name, value, options)
* url(name, value, options)
* color(name, value, options)
* hidden(name, value, options)
* checkbox(name, value, checked, options)
* radio(name, value, checked, options)
* file(name, options)
* password(name, options)
* textarea(name, value, options)
* select(name, list, selected, options)
* button(name, options)
* submit(name, options)

If a method is not supported, there is no replacement.


Warnings and limitations
------------------------

## Escaped echo without double-encode

LaravelCollective internally used most of the time escaped echo without double-encode:

```php
e($value, false)
```

This prevents the encoded HTML entities from being encoded a second time (`&amp;` => `&amp;amp;`)

For example:

```blade
<label>
    {!! e('<strong>Name &amp; firstname</strong>', false) !!}
<label>
```

The HTML result will be:

```html
&lt;strong&gt;Name &amp; firstname&lt;/strong&gt;
```

For simplicity and clarity purpose, this package use regular Blade echo syntax instead of escaped echo without double-encode.

If you want to keep the original behavior of LaravelCollective, use `--escape-without-double-encode` option to the command.

So instead of escaping this way:

```blade
{{ $value }}
```

The values ​​will be escaped like this:

```blade
{!! e($value, false) !!}
```

## Automatically retrieve field value

LaravelCollective has a complex method to automatically retrieve the value of the field: it searches in "old" values
and in the request. You can see it in the `getValueAttribute` method of the `FormBuilder` class.

This was to complex to implement entirely, so the converter only handles the value retrieving from the "old" values.

For example:

```blade
{!! Form::text('name') !!}
```

Will be replaced by:

```blade
<input
    type="text"
    name="name"
    id="name"
    value="{!! e(old('name'), false) !!}"
>
```

If you have fields with name as array, the converter will replace the array syntax by dot syntax for the `old` helper
like LaravelCollective do.

For example:

```blade
{!! Form::text('name[0]') !!}
```

Will be replaced by:

```blade
<input
    type="text"
    name="name[0]"
    value="{!! e(old('name.0'), false) !!}"
>
```

If you already used `old` helper in the `Form::` syntax, the converter will detect it and not doubling the use of the
"old" helper.

**WARNING 1:** If a part of the name is in a variable (for example `Form::text('name['.$index.']')`), the converter will
integrate the replacement function into the output result like this:

```blade
<input
    type="text"
    name="name[0]"
    value="{!! e(old(str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], 'name['.$index.']')), false) !!}"
>
```

**WARNING 2:** If you have fields with name as array but with no explicit key (for example `name[]`), the converter cannot
determine what index to use to get the proper value and will simply render `old('name')` instead of `old('name.<index>')`.
You may need to check these cases to manually set the proper way to retrieve the value (for example: `old('name.0')`).

## Automatically determine radio and checkbox checked state

Like for value retrieving, the converter only handles the checked state retrieving from the "old" values. It uses `in_array`
in case where the "old" value is multiple.

For example:

```blade
{!! Form::checkbox('name[]') !!}
```

Will be replaced by:

```blade
<input
    type="checkbox"
    name="name[]"
    value="1"
    @checked (in_array('1', (array) old('name')))
>
```

If a default checked state is specify, for example:

```blade
{!! Form::checkbox('name[]', '1', true) !!}
```

It will appear like this:

```blade
<input
    type="checkbox"
    name="name[]"
    value="1"
    @checked (! old() ? true : in_array('1', (array) old('name')))
>
```

## Select with optgroup

The `Form::select` method accepts grouped arrays of options to render `<optgroup>`.

This feature was to complex to implement: this would have required to add to much code in the HTML replacement whose
will be not needed 99% of the time (unless you heavy used optgroups). The converter cannot detect if the `list` argument
contains groups so it is not possible to detect cases where optgroups are used. You may need to manually review these cases.

However, the converter can detect if `optionsAttributes` or `optgroupsAttributes` arguments of `Form::builder` have been
used. If so, the corresponding `Form::select` will not be replaced.

## Comments

If there are comments in the original syntax, the converter will erase these but the original syntax will be preserve
in a comment for manual check. You can retrieve these cases by searching for `@TODO CHECK COMMENTS`.

For example:

```blade
{!! Form::text('name', null, [
    'class' => 'form-control',
    // 'required',
]) !!}
```

Will be replaced by:

```blade
{{-- @TODO CHECK COMMENTS: {!! Form::text('name', null, [
    'class' => 'form-control',
    // 'required',
]) !!} --}}
<input
    type="text"
    name="name"
    id="name"
    value="{!! e(old('name'), false) !!}"
>
```

## Not regular array syntax for options

If the options argument is not a regular array, the converter will process the replacement of the `Form::` syntax
but will preserve the options in a comment for manual check and manual replacements of the HTML tag attributes.
You can retrieve these cases by searching for `@TODO CHECK OPTIONS`.

For example:

```blade
{!! Form::text('name', null,
    array_merge($defaultOptions, [
        'size' => 50,
        'required',
    ])
) !!}
```

Will be replaced by:

```blade
<input
    type="text"
    name="name"
    id="name"
    value="{!! e(old('name'), false) !!}"
    {{-- @TODO CHECK OPTIONS: array_merge($defaultOptions, [
        'size' => 50,
        'required',
    ]) --}}
>
```

## Date fields

LaravelCollective `date`, `time`, `datetime`, `week` and `month` methods support a `DateTime` instance as value and format
this internally. The converter cannot detect the value type so you may need to review these cases if `DateTime` instances
have been used as value argument of these methods.
