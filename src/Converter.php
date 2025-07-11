<?php

namespace Axn\LaravelCollectiveFormToRawHtml;

use Symfony\Component\Finder\SplFileInfo;

class Converter
{
    public const CHECK_COMMENTS_TAG = '@TODO CHECK COMMENTS';

    public const CHECK_OPTIONS_TAG = '@TODO CHECK OPTIONS';

    public static bool $escapeWithoutDoubleEncode = false;

    protected static string $indent = '';

    protected static bool $hasComments = false;

    /**
     * @param  array<SplFileInfo>  $files
     */
    public static function execute(array $files): int
    {
        $nbReplacements = 0;

        foreach ($files as $file) {
            $content = $file->getContents();

            preg_match_all('/([ |\t]*)\{(\{|!!)\s*Form::(\w+)\((.*)\)\s*(\}|!!)\}/Us', $content, $matches);

            foreach (array_keys($matches[0]) as $i) {
                if (strpos($matches[0][$i], '{{--') !== false || strpos($matches[0][$i], '--}}') !== false) {
                    continue;
                }

                $result = null;
                static::$hasComments = false;
                static::$indent = $matches[1][$i];
                $formBuilderMethod = $matches[3][$i];

                try {
                    $formBuilderArgs = static::extractArgsFromString($matches[4][$i]);

                    if ($formBuilderMethod === 'open') {
                        $result = static::buildFormOpen(
                            $formBuilderArgs[0] ?? ''
                        );

                    } elseif ($formBuilderMethod === 'close') {
                        $result = static::buildFormClose();

                    } elseif ($formBuilderMethod === 'hiddenForm') {
                        $result = static::buildHiddenForm(
                            $formBuilderArgs[0] ?? ''
                        );

                    } elseif (\in_array($formBuilderMethod, ['label', 'labelRequired'])) {
                        $result = static::buildLabel(
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? '',
                            $formBuilderArgs[2] ?? '',
                            $formBuilderArgs[3] ?? 'true',
                            $formBuilderMethod === 'labelRequired'
                        );

                    } elseif (\in_array($formBuilderMethod, ['input', 'text', 'number', 'date', 'time', 'datetime', 'week', 'month', 'range', 'search', 'email', 'tel', 'url', 'color', 'hidden'])) {
                        if ($formBuilderMethod === 'input') {
                            $formBuilderMethod = trim(array_shift($formBuilderArgs), '\'"');
                        }

                        $result = static::buildDefaultInput(
                            $formBuilderMethod,
                            $formBuilderArgs[0] ?? '',
                            $formBuilderArgs[1] ?? '',
                            '',
                            $formBuilderArgs[2] ?? ''
                        );

                    } elseif (\in_array($formBuilderMethod, ['checkbox', 'radio'])) {
                        $result = static::buildDefaultInput(
                            $formBuilderMethod,
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? ($formBuilderMethod === 'checkbox' ? "'1'" : ''),
                            $formBuilderArgs[2] ?? '',
                            $formBuilderArgs[3] ?? ''
                        );

                    } elseif (\in_array($formBuilderMethod, ['file', 'password'])) {
                        $result = static::buildNoValueInput(
                            $formBuilderMethod,
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? ''
                        );

                    } elseif ($formBuilderMethod === 'textarea') {
                        $result = static::buildTextarea(
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? '',
                            $formBuilderArgs[2] ?? ''
                        );

                    } elseif ($formBuilderMethod === 'select') {
                        if (! static::isEmpty($formBuilderArgs[4] ?? '')
                            || ! static::isEmpty($formBuilderArgs[5] ?? '')) {

                            continue;
                        }

                        $result = static::buildSelect(
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? '[]',
                            $formBuilderArgs[2] ?? '',
                            $formBuilderArgs[3] ?? ''
                        );

                    } elseif (\in_array($formBuilderMethod, ['button', 'submit'])) {
                        $result = static::buildButton(
                            $formBuilderMethod,
                            $formBuilderArgs[0],
                            $formBuilderArgs[1] ?? ''
                        );
                    }

                    if ($result) {
                        if (static::$hasComments) {
                            $result = '{{-- '.static::CHECK_COMMENTS_TAG.': '.$matches[0][$i].' --}}'."\n".$result;
                        }

                        $content = str_replace($matches[0][$i], $result, $content);
                        $nbReplacements++;
                    }

                } catch (ConverterException) {
                }
            }

            file_put_contents($file->getPathname(), $content);
        }

        return $nbReplacements;
    }

    protected static function buildFormOpen(string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        if (! isset($extractedOptions['method'])) {
            $method = 'POST';
        } elseif (preg_match('/^\s*(\'|")(\w+)(\'|")\s*$/Us', $extractedOptions['method'], $matches)) {
            $method = strtoupper($matches[2]);
        } else {
            $method = $extractedOptions['method'];
        }

        if (\in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $attributes['method'] = 'POST';
        } else {
            $attributes['method'] = $method;
        }

        if (isset($extractedOptions['url'])) {
            $attributes['action'] = $extractedOptions['url'];

            unset($extractedOptions['url']);

        } elseif (isset($extractedOptions['route'])) {
            $routeArgs = static::extractArgsFromString(trim($extractedOptions['route'], " \n\r\t\v\0[]"));
            $route = array_shift($routeArgs);

            $attributes['action'] = 'route('.$route;

            if (\count($routeArgs) === 1) {
                $attributes['action'] .= ', '.$routeArgs[0];

            } elseif (\count($routeArgs) > 1) {
                $attributes['action'] .= ', ['.implode(', ', $routeArgs).']';
            }

            $attributes['action'] .= ')';

            unset($extractedOptions['route']);
        }

        if (isset($extractedOptions['files'])) {
            if (\in_array(strtolower($extractedOptions['files']), ['true', '1'])) {
                $attributes['enctype'] = "'multipart/form-data'";

            } elseif (! static::isEmpty($extractedOptions['files'])) {
                $attributes[] = $extractedOptions['files'].' ? \'enctype="multipart/form-data"\' : \'\'';
            }

            unset($extractedOptions['files']);
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<form'.static::buildHtmlTagAttributes($attributes).'>';

        if (! \in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            $input .= "\n".static::$indent.'    @if (strtoupper('.$method.') !== \'GET\')';
            $input .= "\n".static::$indent.'        @csrf';
            $input .= "\n".static::$indent.'        @if (strtoupper('.$method.') !== \'POST\')';
            $input .= "\n".static::$indent.'            @method('.$method.')';
            $input .= "\n".static::$indent.'        @endif';
            $input .= "\n".static::$indent.'    @endif';

        } elseif ($method !== 'GET') {
            $input .= "\n".static::$indent.'    @csrf';

            if ($method !== 'POST') {
                $input .= "\n".static::$indent.'    @method(\''.$method.'\')';
            }
        }

        return $input;
    }

    protected static function buildFormClose(): string
    {
        return static::$indent.'</form>';
    }

    protected static function buildHiddenForm(string $options): string
    {
        $input = static::$indent.'@push(\'extra-html\')'."\n";
        $input .= static::buildFormOpen($options)."\n";
        $input .= static::buildFormClose()."\n";
        $input .= static::$indent.'@endpush';

        return $input;
    }

    protected static function buildLabel(string $for, string $value, string $options, string $escape, bool $required): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        if (! static::isEmpty($for)) {
            $attributes['for'] = $for;

            if (static::isEmpty($value)) {
                $value = "ucwords(str_replace('_', ' ', $for))";
            }
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<label'.static::buildHtmlTagAttributes($attributes).'>'."\n";
        $input .= static::$indent.'    '.static::withEchoIfNeeded($value, ! static::isEmpty($escape))."\n";

        if ($required) {
            $input .= static::$indent.'    <x-required-field-marker />'."\n";
        }

        $input .= static::$indent.'</label>';

        return $input;
    }

    protected static function buildDefaultInput(string $type, string $name, string $value, string $checked, string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        $attributes['type'] = $type;

        if (! static::isEmpty($name)) {
            $attributes['name'] = $name;

            if (\in_array($type, ['checkbox', 'radio'])) {
                if (! static::useOldHelper($checked) && ! static::isEmpty($value)) {
                    $checked = (! static::isEmpty($checked) ? '! old() ? '.$checked.' : ' : '')
                        .'in_array('.$value.', (array) '.static::withOldHelperIfNeeded($name).')';
                }
            } else {
                $value = static::withOldHelperIfNeeded($name, $value);
            }
        }

        if ($type !== 'radio' && empty($extractedOptions['id']) && static::canUseNameAsId($name)) {
            $attributes['id'] = $name;
        }

        $attributes['value'] = $value;

        if (! static::isEmpty($checked)) {
            $attributes['checked'] = $checked;
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<input'.static::buildHtmlTagAttributes($attributes).'>';

        return $input;
    }

    protected static function buildNoValueInput(string $type, string $name, string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        $attributes['type'] = $type;

        if (! static::isEmpty($name)) {
            $attributes['name'] = $name;
        }

        if (empty($extractedOptions['id']) && static::canUseNameAsId($name)) {
            $attributes['id'] = $name;
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<input'.static::buildHtmlTagAttributes($attributes).'>';

        return $input;
    }

    protected static function buildTextarea(string $name, string $value, string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        if (! static::isEmpty($name)) {
            $attributes['name'] = $name;
        }

        if (empty($extractedOptions['id']) && static::canUseNameAsId($name)) {
            $attributes['id'] = $name;
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<textarea'.static::buildHtmlTagAttributes($attributes).'>';
        $input .= static::withEscapedEchoIfNeeded(static::withOldHelperIfNeeded($name, $value));
        $input .= '</textarea>';

        return $input;
    }

    protected static function buildSelect(string $name, string $list, string $selectedValue, string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        if (! static::isEmpty($name)) {
            $attributes['name'] = $name;
        }

        if (empty($extractedOptions['id']) && static::canUseNameAsId($name)) {
            $attributes['id'] = $name;
        }

        if (isset($extractedOptions['placeholder']) && ! static::isEmpty($extractedOptions['placeholder'])) {
            $placeholderOption = static::$indent.'    <option value="">'.static::withEscapedEchoIfNeeded($extractedOptions['placeholder']).'</option>'."\n";

            unset($extractedOptions['placeholder']);
        } else {
            $placeholderOption = '';
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<select'.static::buildHtmlTagAttributes($attributes).'>'."\n";
        $input .= $placeholderOption;
        $input .= static::$indent.'    @foreach ('.$list.' as $optionValue => $optionText)'."\n";
        $input .= static::$indent.'        <option '."\n";
        $input .= static::$indent.'            value="'.static::escapedEcho('$optionValue').'" '."\n";
        $input .= static::$indent.'            @selected(in_array($optionValue, (array) ('.static::withOldHelperIfNeeded($name, $selectedValue).')))'."\n";
        $input .= static::$indent.'        >'.static::escapedEcho('$optionText').'</option>'."\n";
        $input .= static::$indent.'    @endforeach'."\n";
        $input .= static::$indent.'</select>';

        return $input;
    }

    protected static function buildButton(string $type, string $value, string $options): string
    {
        $extractedOptions = static::extractArrayFromStringWithCheckOptionsTagIfFailed($options);
        $attributes = [];

        if ($type === 'button' && isset($extractedOptions['type'])) {
            $attributes['type'] = $extractedOptions['type'];
        } else {
            $attributes['type'] = $type;
        }

        $attributes += $extractedOptions;

        $input = static::$indent.'<button'.static::buildHtmlTagAttributes($attributes).'>'."\n";
        $input .= static::$indent.'    '.static::withRawEchoIfNeeded($value)."\n";
        $input .= static::$indent.'</button>';

        return $input;
    }

    protected static function buildHtmlTagAttributes(array $attributes): string
    {
        $builtAttributes = '';
        $stringBefore = '';
        $stringAfter = '';

        if (\count($attributes) > 1) {
            $stringBefore = '    ';
            $stringAfter = "\n".static::$indent;

            $builtAttributes .= $stringAfter;

        } elseif (\count($attributes) === 1) {
            $builtAttributes .= ' ';
        }

        foreach ($attributes as $attrName => $attrValue) {
            if ($attrName === static::CHECK_OPTIONS_TAG) {
                $builtAttributes .= $stringBefore.'{{-- '.$attrName.': '.$attrValue.' --}}'.$stringAfter;

                continue;
            }

            if (static::isEmpty($attrValue)) {
                $attrValue = '';
            }

            if (\in_array($attrName, ['disabled', 'readonly', 'required', 'checked', 'multiple'])) {
                if ($attrValue === '') {
                    continue;
                }

                if (\in_array(strtolower($attrValue), ['true', '1', $attrName])) {
                    $attr = $attrName;
                } elseif ($attrName === 'multiple') {
                    $attr = '@if ('.$attrValue.') multiple @endif';
                } else {
                    $attr = '@'.$attrName.'('.$attrValue.')';
                }

            } elseif ($attrName === 'class' && preg_match('/^\s*(\[\s*.*\s*\])\s*$/Us', $attrValue, $matches)) {
                $attr = $attrName.'="{!! implode(\' \', '.$matches[1].') !!}"';

            } elseif (\is_string($attrName)) {
                $attr = $attrName.'="'.static::withEscapedEchoIfNeeded($attrValue).'"';

            } else {
                $attr = static::withRawEchoIfNeeded($attrValue);
            }

            $builtAttributes .= $stringBefore.$attr.$stringAfter;
        }

        return $builtAttributes;
    }

    protected static function canUseNameAsId(string $name): bool
    {
        return ! static::isEmpty($name) && preg_match('/^\w+$/', trim($name, '\'"'));
    }

    protected static function isEmpty(string $value): bool
    {
        return \in_array(strtolower($value), ['', "''", '""', 'false', 'null']);
    }

    protected static function escapedEcho(string $value): string
    {
        if (static::$escapeWithoutDoubleEncode) {
            return '{!! e('.$value.', false) !!}';
        }

        return '{{ '.$value.' }}';
    }

    /**
     * Examples:
     *
     * $value = "'Bonjour l\'ami'" + $escape = false|true
     * return = "Bonjour l'ami"
     *
     * $value = "'Bonjour <strong>l\'ami'</strong>" + $escape = false
     * return = "Bonjour <strong>l'ami</strong>"
     *
     * $value = "'Bonjour <strong>l\'ami'</strong>" + $escape = true
     * return = "{!! e('Bonjour <strong>l\'ami</strong>', false) !!}"
     *
     * $value = "'Bonjour l\'ami '.$name" + $escape = false
     * return = "{!! 'Bonjour l\'ami '.$name !!}"
     *
     * $value = "'Bonjour l\'ami '.$name" + $escape = true
     * return = "{!! e('Bonjour l\'ami '.$name, false) !!}"
     */
    protected static function withEchoIfNeeded(string $value, bool $escape): string
    {
        if (static::isEmpty($value) || preg_match('/^\w+$/', $value)) {
            return $value;
        }

        $unquotedValue = static::extractStringBetweenQuotes($value);

        if ($unquotedValue !== null && (! $escape || $unquotedValue === strip_tags($unquotedValue))) {
            return $unquotedValue;
        }

        if ($escape) {
            return static::escapedEcho($value);
        }

        return '{!! '.$value.' !!}';
    }

    protected static function withEscapedEchoIfNeeded(string $value): string
    {
        return static::withEchoIfNeeded($value, true);
    }

    protected static function withRawEchoIfNeeded(string $value): string
    {
        return static::withEchoIfNeeded($value, false);
    }

    protected static function withOldHelperIfNeeded(string $name, string $value = ''): string
    {
        if (static::useOldHelper($value) || static::isEmpty($name)) {
            return $value;
        }

        if (static::extractStringBetweenQuotes($name) !== null) {
            $key = str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $name);
        } else {
            $key = "str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $name)";
        }

        return 'old('.$key.(! empty($value) ? ', '.$value : '').')';
    }

    protected static function useOldHelper(string $value): bool
    {
        return preg_match('/[^\w]old\(/', ' '.$value);
    }

    /**
     * Examples:
     *
     * $string = "'test'"
     * return = "test"
     *
     * $string = "'Bonjour l\'ami'"
     * return = "Bonjour l'ami"
     *
     * $string = "'Bonjour l\'ami '.$name"
     * return = null
     *
     * $string = "true"
     * return = null
     */
    protected static function extractStringBetweenQuotes(string $string): ?string
    {
        if (preg_match('/^\'(.*)\'$/Us', $string, $matches)
            && strpos(str_replace("\\'", '', $matches[1]), "'") === false) {

            return str_replace("\\'", "'", $matches[1]);
        }

        if (preg_match('/^"([^\$]*)"$/Us', $string, $matches)
            && strpos(str_replace('\\"', '', $matches[1]), '"') === false) {

            return str_replace('\\"', '"', $matches[1]);
        }

        return null;
    }

    protected static function extractArrayFromStringWithCheckOptionsTagIfFailed(string $string): array
    {
        try {
            $options = static::extractArrayFromString($string);

        } catch (ConverterException) {
            $options = [
                static::CHECK_OPTIONS_TAG => $string,
            ];
        }

        return $options;
    }

    /**
     * Example:
     *
     * $string = "['class' => 'form-control', 'disabled']"
     *
     * return = [
     *     "class" => "'form-control'",
     *     0 => "'disabled'",
     * ]
     */
    protected static function extractArrayFromString(string $string): array
    {
        $array = [];

        if (empty($string)) {
            return $array;
        }

        if (! preg_match('/^\s*\[\s*(.*)\s*\]\s*$/Us', $string, $matches)) {
            throw new ConverterException();
        }

        $segments = static::extractArgsFromString($matches[1]);

        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue;
            }

            if (preg_match('/^("|\')(.+)("|\')\s*=>\s*(.+)$/Us', $segment, $matches)) {
                $key = strtolower(trim($matches[2]));
                $value = trim($matches[4]);

                $array[$key] = $value;

            } elseif (strpos($segment, '=>') !== false) {
                throw new ConverterException();
            } else {
                $array[] = $segment;
            }
        }

        return $array;
    }

    /**
     * Example:
     *
     * $string = "'champ', $value, ['class' => 'form-control', 'disabled']"
     *
     * return = [
     *     "'champ'",
     *     "$value",
     *     "['class' => 'form-control', 'disabled']",
     * ]
     */
    protected static function extractArgsFromString(string $string): array
    {
        $args = [];
        $argIndex = 0;

        foreach (explode(',', $string) as $segment) {
            if (! isset($args[$argIndex])) {
                $args[$argIndex] = '';

                $inSinglelineComment = false;
                $inMultilineComment = false;
                $inSimpleQuotedString = false;
                $inDoubleQuotedString = false;
                $nbUnclosedParenthesis = 0;
                $nbUnclosedBrackets = 0;

            } elseif (empty($inSinglelineComment) && empty($inMultilineComment)) {
                $args[$argIndex] .= ',';
            }

            $chars = mb_str_split($segment);

            foreach ($chars as $i => $char) {
                $previousChar = $chars[$i - 1] ?? '';
                $nextChar = $chars[$i + 1] ?? '';

                if ($inSinglelineComment || $inMultilineComment) {
                    static::$hasComments = true;

                    if ($inSinglelineComment && $char === "\n") {
                        $inSinglelineComment = false;

                    } elseif ($inMultilineComment && $previousChar === '*' && $char === '/') {
                        $inMultilineComment = false;
                    }

                    continue;
                }

                if (! $inDoubleQuotedString && $char === "'" && $previousChar !== '\\') {
                    $inSimpleQuotedString = ! $inSimpleQuotedString;

                } elseif (! $inSimpleQuotedString && $char === '"' && $previousChar !== '\\') {
                    $inDoubleQuotedString = ! $inDoubleQuotedString;
                }

                if (! $inSimpleQuotedString && ! $inDoubleQuotedString) {
                    if ($char === '(') {
                        $nbUnclosedParenthesis++;

                    } elseif ($char === ')') {
                        $nbUnclosedParenthesis--;

                    } elseif ($char === '[') {
                        $nbUnclosedBrackets++;

                    } elseif ($char === ']') {
                        $nbUnclosedBrackets--;

                    } elseif ($char === '#' || $char === '/' && $nextChar === '/') {
                        $inSinglelineComment = true;
                        continue;

                    } elseif ($char === '/' && $nextChar === '*') {
                        $inMultilineComment = true;
                        continue;
                    }
                }

                if ($nbUnclosedParenthesis < 0 || $nbUnclosedBrackets < 0) {
                    throw new ConverterException();
                }

                $args[$argIndex] .= $char;
            }

            if (! $inSinglelineComment && ! $inMultilineComment
                && ! $inSimpleQuotedString && ! $inDoubleQuotedString
                && $nbUnclosedParenthesis === 0 && $nbUnclosedBrackets === 0) {

                $args[$argIndex] = trim($args[$argIndex]);
                $argIndex++;
            }
        }

        return $args;
    }
}
