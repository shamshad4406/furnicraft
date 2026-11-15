<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Blade only recognises a directive at a word boundary, so `email@if (...)`
 * is left in the output as literal text while its `@endif` *is* compiled —
 * the result is a PHP syntax error at render time, in whichever view happens
 * to be rendered. These checks catch it in CI instead of in production.
 */
class BladeHygieneTest extends TestCase
{
    /**
     * @return string[]
     */
    protected function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_there_are_blade_files_to_check(): void
    {
        $this->assertGreaterThan(50, count($this->bladeFiles()));
    }

    public function test_no_directive_is_glued_to_a_word_character(): void
    {
        $directives = implode('|', [
            'if', 'elseif', 'else', 'endif', 'unless', 'endunless',
            'foreach', 'endforeach', 'forelse', 'empty', 'endforelse',
            'for', 'endfor', 'while', 'endwhile',
            'isset', 'endisset', 'php', 'endphp',
            'switch', 'case', 'default', 'break', 'continue', 'endswitch',
            'can', 'cannot', 'endcan', 'endcannot', 'auth', 'endauth', 'guest', 'endguest',
            'include', 'extends', 'section', 'endsection', 'yield', 'stack', 'push', 'endpush',
            'csrf', 'method', 'json', 'class', 'checked', 'selected', 'disabled',
        ]);

        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all('/\w@('.$directives.')\b/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file)
                        .':'.$line.' ('.trim($match).')';
                }
            }
        }

        $this->assertSame([], $offenders,
            "Blade will not compile a directive glued to a word character:\n  ".implode("\n  ", $offenders)
            ."\nPut a space before it, or build the string in PHP."
        );
    }

    /**
     * `.fc-panel` must not force its own height.
     *
     * A column often stacks several panels — checkout stacks four — and
     * `height: 100%` on each made every one as tall as the entire column, so
     * the later panels ran off the end of the page and rendered straight
     * through the footer. Where two panels side by side should match, the
     * view asks for it with Bootstrap's `h-100`.
     */
    public function test_a_panel_does_not_force_its_own_height(): void
    {
        $css = public_path('assets/css/app.css');

        if (! file_exists($css)) {
            $this->markTestSkipped('Stylesheet not built — run `npm run build`.');
        }

        preg_match('/\.fc-panel\s*\{([^}]*)\}/', (string) file_get_contents($css), $match);

        $this->assertNotEmpty($match, '.fc-panel is missing from the stylesheet.');
        $this->assertStringNotContainsString('height:100%', str_replace(' ', '', $match[1]),
            '.fc-panel must size to its content; stacked panels break otherwise.');
    }

    public function test_every_view_compiles_to_valid_php(): void
    {
        $broken = [];

        foreach ($this->bladeFiles() as $file) {
            $compiled = Blade::compileString((string) file_get_contents($file));

            // php -l needs a file; keep it out of the project tree.
            $tmp = tempnam(sys_get_temp_dir(), 'blade').'.php';
            file_put_contents($tmp, $compiled);

            $output = [];
            $status = 0;
            exec('php -l '.escapeshellarg($tmp).' 2>&1', $output, $status);
            @unlink($tmp);

            if ($status !== 0) {
                $name = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file);
                $broken[] = $name.' — '.preg_replace('/\s+/', ' ', implode(' ', $output));
            }
        }

        $this->assertSame([], $broken, "These views do not compile:\n  ".implode("\n  ", $broken));
    }
}
