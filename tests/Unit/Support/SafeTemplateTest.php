<?php

declare(strict_types=1);

use App\Support\Templates\SafeTemplate;

it('substitutes variables and dotted paths', function (): void {
    $template = new SafeTemplate;

    expect($template->render('Hi {{ name }}, request {{ request.reference }}', [
        'name' => 'Sara',
        'request' => ['reference' => 'REQ-2026-000001'],
    ]))->toBe('Hi Sara, request REQ-2026-000001');
});

it('escapes html by default and leaves unknown variables empty', function (): void {
    $template = new SafeTemplate;

    expect($template->render('{{ name }}|{{ missing }}', ['name' => '<b>x</b>']))
        ->toBe('&lt;b&gt;x&lt;/b&gt;|');
});

it('never evaluates code or blade syntax', function (): void {
    $template = new SafeTemplate;

    expect($template->render('{{ phpinfo() }} @php echo 1; @endphp {!! $x !!}', []))
        ->toBe('{{ phpinfo() }} @php echo 1; @endphp {!! $x !!}');
});

it('applies filters', function (): void {
    $template = new SafeTemplate;

    expect($template->render('{{ name | upper }} {{ day | date }}', ['name' => 'sara', 'day' => '2026-10-13'], 'en'))
        ->toBe('SARA October 13, 2026');
});

it('validates templates against an allow-list', function (): void {
    $template = new SafeTemplate;

    $template->assertValid('{{ name }} {{ day | date }}', ['name', 'day']);

    expect(fn () => $template->assertValid('{{ salary }}', ['name']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $template->assertValid('{{ name | shell }}', ['name']))->toThrow(InvalidArgumentException::class);
});
