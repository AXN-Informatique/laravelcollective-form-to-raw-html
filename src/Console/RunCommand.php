<?php

namespace Axn\LaravelCollectiveFormToRawHtml\Console;

use Axn\LaravelCollectiveFormToRawHtml\Converter;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class RunCommand extends Command
{
    protected $signature = 'laravelcollective-form-to-raw-html:run
        {target=resources/views : Target path to scan (directory or single file relative to the project root)}
        {--escape-with-double-encode : Use regular Blade echo syntax instead of the one without double-encode (see README for more info)}';

    protected $description = 'Replaces LaravelCollective `Form::` syntax by raw HTML';

    public function handle(): int
    {
        $target = $this->argument('target');

        $path = base_path($target);

        if (is_file($path)) {
            $finder = Finder::create()
                ->files()
                ->in(\dirname($path))
                ->depth(0)
                ->name(basename($path));

            $this->comment('Replacing `Form::` syntax by raw HTML in file `'.$path.'`...');

        } elseif (is_dir($path)) {
            $finder = Finder::create()
                ->files()
                ->in($path);

            $this->comment('Replacing `Form::` syntax by raw HTML in all files of directory `'.$path.'`...');

        } else {
            $this->error('Target `'.$path.'` not found.');

            return 0;
        }

        $files = iterator_to_array($finder, false);

        Converter::$escapeWithDoubleEncode = $this->option('escape-with-double-encode');

        $nbReplacements = Converter::execute($files);

        $this->info('Finished with '.$nbReplacements.' replacement(s) done.');

        $this->line('Remember to search and review `'.Converter::CHECK_COMMENTS_TAG.'`');
        $this->line('Remember to search and review `'.Converter::CHECK_OPTIONS_TAG.'`');
        $this->line('Remember to search and replace leaving `Form::`');

        $this->comment('See README.md for more info.');

        return 0;
    }
}
