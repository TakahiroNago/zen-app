<?php

use App\Services\ConsultationResponseFormatter;

function formatResponse(string $markdown): string
{
    return (new ConsultationResponseFormatter)->toHtml($markdown);
}

test('markdown headings, lists and emphasis are rendered as html', function () {
    $html = formatResponse("### 1. 見出し\n\n- 項目\n\n1. 番号付き\n\n**重要**です。*強調*も使えます。");

    expect($html)->toContain('<h3>1. 見出し</h3>')
        ->and($html)->toContain('<li>項目</li>')
        ->and($html)->toContain('<ol>')
        ->and($html)->toContain('<strong>重要</strong>')
        ->and($html)->toContain('<em>強調</em>');
});

test('bold text closed after japanese punctuation is rendered', function () {
    $html = formatResponse('次に、**「相手にどう思われるか」ではなく、「私は今、無理をしていないか」**を確かめてください。');

    expect($html)->toContain('<strong>「相手にどう思われるか」ではなく、「私は今、無理をしていないか」</strong>を')
        ->and($html)->not->toContain('**');
});

test('japanese bold text keeps nested emphasis, links and code inside it', function () {
    $html = formatResponse('まず、**「*今*」と[禅](https://example.com/zen)の`言葉`」**を味わいます。');

    expect($html)->toContain('<strong>「<em>今</em>」と<a href="https://example.com/zen">禅</a>の<code>言葉</code>」</strong>を');
});

test('japanese bold text is escaped and never spans tags, code, links or paragraphs', function () {
    expect(formatResponse('、**「<b>&amp;」**を'))->toContain('<strong>「&amp;」</strong>を')
        ->and(formatResponse('、**「&lt;script&gt;」**を'))->toContain('<strong>「&lt;script&gt;」</strong>を')
        ->and(formatResponse('`、**「a」**を`'))->toContain('<code>、**「a」**を</code>')
        ->and(formatResponse('[、**「a」](https://example.com)**を'))
        ->toContain('<a href="https://example.com">、**「a」</a>**を')
        ->and(formatResponse('[x](https://example.com/、**「a」**を)'))
        ->toContain('<a href="https://example.com/%E3%80%81**%E3%80%8Ca%E3%80%8D**%E3%82%92">x</a>')
        ->and(formatResponse("、**「a」\n\n」**を"))->not->toContain('<strong>');
});

test('escaped asterisks and unpaired delimiters stay literal', function () {
    expect(formatResponse('、\*\*「a」\*\*を'))->toContain('、**「a」**を')
        ->and(formatResponse('、**「a」'))->toContain('、**「a」')
        ->and(formatResponse('、** 「a」 **を'))->not->toContain('<strong>');
});

test('raw html in responses is not rendered', function (string $markdown, string $forbidden) {
    expect(formatResponse($markdown))->not->toContain($forbidden);
})->with([
    'script tag' => ["<script>alert('unsafe')</script>", '<script'],
    'inline event handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'inline html link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
    'iframe block' => ["<iframe src=\"https://example.com\"></iframe>\n", '<iframe'],
    'svg payload' => ['<svg onload="alert(1)">', '<svg'],
]);

test('dangerous url schemes are not rendered as links', function (string $markdown) {
    $html = formatResponse($markdown);

    expect(strtolower($html))->not->toContain('href="javascript:')
        ->and(strtolower($html))->not->toContain('href="vbscript:')
        ->and(strtolower($html))->not->toContain('href="data:');
})->with([
    'javascript link' => ['[x](javascript:alert(1))'],
    'mixed case javascript link' => ['[x](JaVaScRiPt:alert(1))'],
    'entity encoded javascript link' => ['[x](&#106;avascript:alert(1))'],
    'vbscript link' => ['[x](vbscript:msgbox(1))'],
    'data link' => ['[x](data:text/html;base64,PHNjcmlwdD4=)'],
    'reference link' => ["[x][a]\n\n[a]: javascript:alert(1)"],
    'autolink' => ['<javascript:alert(1)>'],
]);

test('link attributes are escaped', function () {
    expect(formatResponse('[x](https://example.com "a\" onmouseover=\"alert(1)")'))
        ->toContain('title="a&quot; onmouseover=&quot;alert(1)"');
});

test('images are never rendered and keep only their alt text', function (string $markdown) {
    $html = formatResponse($markdown);

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('tracker.example')
        ->and($html)->toContain('説明');
})->with([
    'inline image' => ['![説明](https://tracker.example/pixel.png)'],
    'reference image' => ["![説明][img]\n\n[img]: https://tracker.example/pixel.png"],
    'image inside link' => ['[![説明](https://tracker.example/pixel.png)](https://example.com)'],
    'image inside japanese bold' => ['、**「![説明](https://tracker.example/pixel.png)」**を'],
]);
